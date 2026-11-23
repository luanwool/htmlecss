<?php

namespace App\Services\Club;

use App\Enums\AppointmentStatus;
use App\Enums\CycleStatus;
use App\Enums\PlanUsageMode;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\FinancialEntry;
use App\Models\MembershipPlan;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\SubscriptionNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingPolicy;
use App\Services\Booking\BookingService;
use App\Services\Payments\GatewayPayment;
use App\Services\Payments\GatewaySubscription;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida das assinaturas. "Assinatura autorizada no gateway" NÃO significa mês pago:
 * somente um pagamento aprovado (confirmado na API) marca o ciclo correspondente como pago.
 */
class SubscriptionService
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private AuditLogger $audit,
        private BookingPolicy $policy,
    ) {}

    /** Snapshot das condições comerciais vigentes na adesão. */
    public function snapshot(MembershipPlan $plan): array
    {
        $plan = $plan->fresh(['services', 'barbers']);

        return [
            'name' => $plan->name, 'version' => $plan->version, 'price_cents' => $plan->price_cents,
            'usage_mode' => $plan->usage_mode->value, 'uses_per_cycle' => $plan->uses_per_cycle,
            'services' => $plan->services->mapWithKeys(fn ($s) => [$s->id => $s->pivot->uses_per_cycle])->all(),
            'service_names' => $plan->services->pluck('name', 'id')->all(),
            'all_barbers' => $plan->all_barbers, 'barber_ids' => $plan->barbers->pluck('id')->all(),
            'max_future_bookings' => $plan->max_future_bookings, 'max_per_day' => $plan->max_per_day,
            'max_per_week' => $plan->max_per_week, 'min_days_between_uses' => $plan->min_days_between_uses,
            'min_advance_minutes' => $plan->min_advance_minutes, 'max_advance_days' => $plan->max_advance_days,
            'allowed_weekdays' => $plan->allowed_weekdays, 'allowed_time_start' => $plan->allowed_time_start,
            'allowed_time_end' => $plan->allowed_time_end, 'min_commitment_months' => $plan->min_commitment_months,
            'grace_days' => $plan->grace_days, 'cancellation_policy' => $plan->cancellation_policy,
            'no_show_policy' => $plan->no_show_policy, 'guest_policy' => $plan->guest_policy,
            'benefits' => $plan->benefits, 'terms' => $plan->terms,
        ];
    }

    public function start(Customer $customer, MembershipPlan $plan, string $method): Subscription
    {
        if (! $plan->is_published || $plan->trashed()) {
            throw new BookingException('Plano indisponível.');
        }
        if (! in_array($method, ['card', 'pix'], true)) {
            throw new BookingException('Forma de pagamento inválida.');
        }
        $gateway = $this->gateways->driver();

        [$sub, $cycle] = DB::transaction(function () use ($customer, $plan, $method, $gateway) {
            // Uma assinatura viva por cliente (trava o cliente para evitar adesão dupla).
            Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
            $existing = $customer->subscriptions()->live()->first();
            if ($existing && ! in_array($existing->status, [SubscriptionStatus::Pending, SubscriptionStatus::AwaitingPayment], true)) {
                throw new BookingException('Você já possui uma assinatura ativa.');
            }
            if ($existing) {
                $this->closeUnpaid($existing, 'Substituída por nova tentativa de adesão');
            }

            $sub = Subscription::create([
                'customer_id' => $customer->id, 'membership_plan_id' => $plan->id, 'plan_version' => $plan->version,
                'plan_snapshot' => $this->snapshot($plan), 'price_cents' => $plan->price_cents,
                'status' => SubscriptionStatus::Pending, 'payment_method' => $method, 'gateway' => $gateway->name(),
                'terms_accepted_at' => now(),
            ]);
            $start = BusinessTime::now()->startOfDay();
            $cycle = $this->createCycle($sub, 1, $start);

            return [$sub, $cycle];
        });

        $remote = $gateway->createSubscription($sub, $cycle);
        $sub->update(['gateway_subscription_id' => $remote->id, 'checkout_url' => $remote->checkoutUrl, 'status' => SubscriptionStatus::AwaitingPayment]);
        $this->audit->log('subscription.started', $sub, [], ['plan' => $plan->name, 'version' => $plan->version, 'method' => $method]);

        return $sub;
    }

    public function createCycle(Subscription $sub, int $sequence, CarbonImmutable $periodStartLocal): BillingCycle
    {
        $start = $periodStartLocal->startOfDay();

        return BillingCycle::create([
            'subscription_id' => $sub->id, 'sequence' => $sequence,
            'period_start' => $start->toDateString(),
            'period_end' => $start->addMonthNoOverflow()->toDateString(), // 31/01 → 28/02; nunca "pula" mês
            'due_on' => $start->toDateString(),
            'amount_cents' => $sub->price_cents,
            'allowance' => match ($sub->term('usage_mode')) {
                PlanUsageMode::LimitedGlobal->value => (int) $sub->term('uses_per_cycle'),
                PlanUsageMode::Unlimited->value => null,
                default => null, // por serviço: controlado por serviço no snapshot
            },
            'status' => CycleStatus::Pending,
        ]);
    }

    /**
     * Aplica o estado de um pagamento CONSULTADO na API. Idempotente: reaplicar o mesmo estado não muda nada.
     */
    public function applyPayment(GatewayPayment $p): string
    {
        $cycle = BillingCycle::findByReference($p->externalReference);
        if (! $cycle) {
            return 'unknown_reference';
        }

        return DB::transaction(function () use ($p, $cycle) {
            $cycle = BillingCycle::query()->whereKey($cycle->id)->lockForUpdate()->first();
            $sub = Subscription::query()->whereKey($cycle->subscription_id)->lockForUpdate()->first();

            $tx = PaymentTransaction::query()->firstOrNew(['gateway' => $sub->gateway, 'gateway_payment_id' => $p->id]);
            $tx->fill([
                'subscription_id' => $sub->id, 'billing_cycle_id' => $cycle->id, 'status' => $p->status, 'method' => $p->method,
                'amount_cents' => $p->amountCents, 'fee_cents' => $p->feeCents, 'net_cents' => $p->netCents,
                'paid_at' => $p->paidAt, 'details' => ['external_reference' => $p->externalReference],
            ])->save();

            switch ($p->status) {
                case GatewayPayment::APPROVED:
                    if ($cycle->status === CycleStatus::Paid) {
                        return 'already_paid';
                    }
                    if ($sub->fraud_blocked) {
                        return 'blocked';
                    }
                    $cycle->update(['status' => CycleStatus::Paid, 'gateway_payment_id' => $p->id, 'paid_at' => $p->paidAt ?? now(),
                        'fee_cents' => $p->feeCents, 'net_cents' => $p->netCents, 'receipt_url' => $p->receiptUrl]);
                    if (in_array($sub->status, [SubscriptionStatus::Pending, SubscriptionStatus::AwaitingPayment, SubscriptionStatus::PastDue, SubscriptionStatus::Suspended], true)) {
                        $sub->update(['status' => SubscriptionStatus::Active, 'activated_at' => $sub->activated_at ?? now()]);
                    }
                    FinancialEntry::query()->firstOrCreate(
                        ['source_type' => $tx->getMorphClass(), 'source_id' => $tx->id, 'type' => 'income'],
                        ['category' => 'assinatura', 'amount_cents' => $p->amountCents, 'fee_cents' => (int) $p->feeCents,
                            'occurred_at' => $p->paidAt ?? now(), 'payment_method' => $p->method, 'status' => 'paid',
                            'description' => 'Mensalidade '.$sub->term('name').' — ciclo '.$cycle->sequence]
                    );
                    $this->audit->log('subscription.cycle_paid', $sub, [], ['cycle' => $cycle->sequence, 'payment' => $p->id]);
                    $this->notifyCustomer($sub, 'paid');

                    return 'paid';

                case GatewayPayment::REJECTED:
                case GatewayPayment::CANCELLED:
                    if ($cycle->status === CycleStatus::Paid) {
                        return 'ignored_paid_cycle'; // evento fora de ordem: já há pagamento aprovado
                    }
                    $cycle->update(['status' => CycleStatus::Failed]);
                    if ($cycle->sequence > 1 && in_array($sub->status, [SubscriptionStatus::Active], true)
                        && BusinessTime::now()->toDateString() >= $cycle->period_start->toDateString()) {
                        $sub->update(['status' => SubscriptionStatus::PastDue]);
                    }
                    $this->audit->log('subscription.payment_failed', $sub, [], ['cycle' => $cycle->sequence, 'payment' => $p->id]);
                    $this->notifyCustomer($sub, 'failed');

                    return 'failed';

                case GatewayPayment::REFUNDED:
                case GatewayPayment::CHARGED_BACK:
                    if (in_array($cycle->status, [CycleStatus::Refunded, CycleStatus::ChargedBack], true)) {
                        return 'already_reversed';
                    }
                    $wasPaid = $cycle->status === CycleStatus::Paid;
                    $cycle->update(['status' => $p->status === GatewayPayment::REFUNDED ? CycleStatus::Refunded : CycleStatus::ChargedBack]);
                    if ($wasPaid) {
                        FinancialEntry::query()->firstOrCreate(
                            ['source_type' => $tx->getMorphClass(), 'source_id' => $tx->id, 'type' => 'refund'],
                            ['category' => $p->status === GatewayPayment::CHARGED_BACK ? 'chargeback' : 'estorno', 'amount_cents' => $p->amountCents,
                                'occurred_at' => now(), 'payment_method' => $p->method, 'status' => 'paid',
                                'description' => ($p->status === GatewayPayment::CHARGED_BACK ? 'Chargeback' : 'Estorno').' — ciclo '.$cycle->sequence]
                        );
                    }
                    $sub->update([
                        'status' => $p->status === GatewayPayment::CHARGED_BACK ? SubscriptionStatus::Suspended : SubscriptionStatus::Refunded,
                        'fraud_blocked' => $p->status === GatewayPayment::CHARGED_BACK ? true : $sub->fraud_blocked,
                    ]);
                    $this->handleUnpaidCycleAppointments($cycle);
                    $this->audit->log('subscription.payment_reversed', $sub, [], ['cycle' => $cycle->sequence, 'status' => $p->status]);
                    $this->notifyCustomer($sub, 'refunded');

                    return 'reversed';

                default:
                    return 'pending';
            }
        });
    }

    public function syncGatewaySubscription(GatewaySubscription $remote): string
    {
        $sub = Subscription::where('gateway_subscription_id', $remote->id)->first();
        if (! $sub) {
            return 'unknown_subscription';
        }
        if ($remote->status === 'cancelled' && ! in_array($sub->status, [SubscriptionStatus::CancelRequested, SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            $this->requestCancellation($sub, null, 'Cancelada no provedor de pagamento', callGateway: false);

            return 'cancelled_remotely';
        }

        return 'synced';
    }

    /**
     * Cancelamento a pedido: impede a próxima renovação; o período já pago segue válido até o fim do ciclo.
     */
    public function requestCancellation(Subscription $sub, ?User $actor, ?string $reason = null, bool $callGateway = true): Subscription
    {
        if (in_array($sub->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::CancelRequested], true)) {
            return $sub;
        }
        if ($callGateway && $sub->gateway_subscription_id) {
            $this->gateways->driver($sub->gateway)->cancelSubscription($sub->gateway_subscription_id);
        }

        DB::transaction(function () use ($sub, $reason) {
            $paid = $sub->currentPaidCycle();
            if ($paid) {
                $sub->update(['status' => SubscriptionStatus::CancelRequested, 'cancel_requested_at' => now(),
                    'cancel_effective_on' => $paid->period_end, 'cancel_reason' => $reason]);
                // Ciclos futuros ainda não pagos deixam de existir.
                $sub->cycles()->where('status', CycleStatus::Pending->value)->where('id', '!=', $paid->id)->update(['status' => CycleStatus::Cancelled->value]);
            } else {
                $this->closeUnpaid($sub, $reason ?? 'Cancelada sem período pago vigente');
            }
        });
        $this->audit->log('subscription.cancel_requested', $sub, [], ['effective_on' => $sub->cancel_effective_on?->toDateString(), 'reason' => $reason]);
        $this->notifyCustomer($sub, 'cancel_requested');

        return $sub;
    }

    /** Rotina diária: renovações, atrasos e encerramentos. Idempotente. */
    public function tick(): array
    {
        $today = BusinessTime::now()->toDateString();
        $stats = ['renewed' => 0, 'past_due' => 0, 'ended' => 0];

        // 1) Encerramento de cancelamentos ao fim do período pago.
        Subscription::query()->where('status', SubscriptionStatus::CancelRequested->value)
            ->whereDate('cancel_effective_on', '<=', $today)->get()
            ->each(function (Subscription $s) use (&$stats) {
                $s->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
                $this->audit->log('subscription.cancelled', $s);
                $stats['ended']++;
            });

        // 2) Renovação: cria o próximo ciclo quando o período atual termina.
        Subscription::query()->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])->get()
            ->each(function (Subscription $s) use ($today, &$stats) {
                $last = $s->latestCycle();
                if ($last && $last->period_end->toDateString() <= $today && $last->status === CycleStatus::Paid) {
                    $next = DB::transaction(function () use ($s, $last) {
                        if ($s->cycles()->where('sequence', $last->sequence + 1)->exists()) {
                            return null;
                        }

                        return $this->createCycle($s, $last->sequence + 1, CarbonImmutable::parse($last->period_end->toDateString(), BusinessTime::tz()));
                    });
                    if ($next) {
                        $stats['renewed']++;
                        $this->gateways->driver($s->gateway)->createCharge($next);
                        if ($s->payment_method === 'pix') {
                            $this->notifyCustomer($s, 'renewal_due');
                        }
                    }
                }
                // 3) Período iniciado sem pagamento → atraso (bloqueia novos agendamentos pelo plano).
                $s->refresh();
                $current = $s->latestCycle();
                if ($s->status === SubscriptionStatus::Active && $current && $current->status !== CycleStatus::Paid
                    && $current->period_start->toDateString() <= $today) {
                    $s->update(['status' => SubscriptionStatus::PastDue]);
                    $this->audit->log('subscription.past_due', $s, [], ['cycle' => $current->sequence]);
                    $stats['past_due']++;
                }
            });

        return $stats;
    }

    /** Conciliação: consulta o provedor e reaplica o estado real (recupera webhooks perdidos). */
    public function reconcile(Subscription $sub): array
    {
        $results = [];
        if (! $sub->gateway_subscription_id) {
            return $results;
        }
        $gateway = $this->gateways->driver($sub->gateway);
        foreach ($gateway->listPayments($sub->gateway_subscription_id) as $payment) {
            $results[$payment->id] = $this->applyPayment($payment);
        }
        $results['subscription'] = $this->syncGatewaySubscription($gateway->getSubscription($sub->gateway_subscription_id));
        $sub->update(['last_reconciled_at' => now()]);

        return $results;
    }

    /** Agendamentos de plano em ciclo que deixou de estar pago: política configurável, nunca presumidos válidos. */
    public function handleUnpaidCycleAppointments(BillingCycle $cycle): int
    {
        $affected = Appointment::query()->where('billing_cycle_id', $cycle->id)->where('mode', 'plan')
            ->where('status', AppointmentStatus::Confirmed->value)->where('starts_at', '>', now())->get();

        foreach ($affected as $a) {
            if ($this->policy->unpaidPolicy() === 'cancel') {
                app(BookingService::class)->cancel($a, true, null, 'Mensalidade do plano não paga');
            } else {
                $a->update(['plan_payment_issue' => true]);
                if ($a->customer?->email) {
                    rescue(fn () => $a->customer->notify(new AppointmentNotification($a, 'payment_issue')), report: true);
                }
            }
        }

        return $affected->count();
    }

    private function closeUnpaid(Subscription $sub, string $reason): void
    {
        $sub->cycles()->where('status', CycleStatus::Pending->value)->update(['status' => CycleStatus::Cancelled->value]);
        $sub->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        if ($sub->gateway_subscription_id) {
            rescue(fn () => $this->gateways->driver($sub->gateway)->cancelSubscription($sub->gateway_subscription_id), report: true);
        }
    }

    private function notifyCustomer(Subscription $sub, string $event): void
    {
        DB::afterCommit(function () use ($sub, $event) {
            if ($sub->customer?->email) {
                rescue(fn () => $sub->customer->notify(new SubscriptionNotification($sub, $event)), report: true);
            }
        });
    }
}
