<?php

namespace App\Services\Club;

use App\Enums\AppointmentStatus;
use App\Enums\PlanUsageMode;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\EntitlementLedgerEntry;
use App\Models\Service;
use App\Models\Subscription;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Direitos do Clube. Regra fundamental: o benefício só vale se existir ciclo PAGO cobrindo a data
 * do atendimento. Todo uso é registrado no ledger (reserva, liberação, consumo, perda) — sem contadores sobrescritos.
 */
class EntitlementService
{
    /** Assinatura que pode estar em uso (inclui cancelamento solicitado, que vale até o fim do período pago). */
    public function usableSubscription(Customer $customer): ?Subscription
    {
        return $customer->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::CancelRequested->value,
                SubscriptionStatus::PastDue->value, SubscriptionStatus::AwaitingPayment->value, SubscriptionStatus::Suspended->value])
            ->latest('id')->first();
    }

    /** IDs de serviços cobertos segundo o snapshot da adesão. */
    public function coveredServiceIds(Subscription $sub): array
    {
        return array_map('intval', array_keys($sub->term('services', [])));
    }

    /**
     * Valida TODAS as regras do plano para usar o benefício nesse horário. Lança BookingException com o motivo.
     * Deve ser chamado dentro da transação da reserva, com a assinatura travada.
     */
    public function assertCanUse(Subscription $sub, Service $service, CarbonImmutable $startUtc, int $barberId, ?int $ignoreAppointmentId = null, bool $walkIn = false): BillingCycle
    {
        if ($sub->fraud_blocked) {
            throw new BookingException('Assinatura bloqueada. Fale com a barbearia.', reason: 'blocked');
        }
        if (! in_array($sub->status, [SubscriptionStatus::Active, SubscriptionStatus::CancelRequested], true)) {
            throw new BookingException(match ($sub->status) {
                SubscriptionStatus::PastDue => 'Mensalidade em atraso: regularize o pagamento para agendar pelo plano.',
                SubscriptionStatus::AwaitingPayment, SubscriptionStatus::Pending => 'Seu plano será liberado assim que o pagamento for confirmado.',
                default => 'Sua assinatura não está ativa ('.$sub->status->label().').',
            }, reason: 'unpaid');
        }

        $cycle = $sub->paidCycleCovering($startUtc);
        if (! $cycle) {
            throw new BookingException('Esta data cai em um mês do plano que ainda não foi pago. Escolha uma data dentro do período pago ou agende como avulso.', reason: 'unpaid_cycle');
        }
        $local = $startUtc->setTimezone(BusinessTime::tz());
        if ($sub->cancel_effective_on && $local->toDateString() >= $sub->cancel_effective_on->toDateString()) {
            throw new BookingException('Sua assinatura foi cancelada e não cobre esta data.', reason: 'cancelled');
        }

        $services = $sub->term('services', []);
        if (! array_key_exists($service->id, $services) && ! array_key_exists((string) $service->id, $services)) {
            throw new BookingException('Este serviço não está incluído no seu plano.', reason: 'not_covered');
        }
        if (! $sub->term('all_barbers', true) && ! in_array($barberId, $sub->term('barber_ids', []))) {
            throw new BookingException('Este profissional não atende pelo seu plano.', reason: 'barber');
        }
        if ($days = $sub->term('allowed_weekdays')) {
            if (! in_array($local->dayOfWeekIso, $days)) {
                throw new BookingException('Seu plano não vale neste dia da semana.', reason: 'weekday');
            }
        }
        if (($ts = $sub->term('allowed_time_start')) && ($te = $sub->term('allowed_time_end'))) {
            $end = $local->addMinutes($service->effectiveDurationMinutes());
            if ($local->format('H:i:s') < $ts || $end->format('H:i:s') > $te) {
                throw new BookingException('Seu plano vale das '.substr($ts, 0, 5).' às '.substr($te, 0, 5).'.', reason: 'time');
            }
        }
        if (! $walkIn) {
            if (($min = $sub->term('min_advance_minutes')) && $startUtc->lt(now()->addMinutes($min))) {
                throw new BookingException("Pelo plano, agende com pelo menos {$min} minutos de antecedência.", reason: 'advance');
            }
            if (($max = $sub->term('max_advance_days')) && $startUtc->gt(now()->addDays($max))) {
                throw new BookingException("Pelo plano, agende com até {$max} dias de antecedência.", reason: 'advance');
            }
        }

        // Saldo do ciclo (reservas + usos) — reservas do próprio agendamento são ignoradas em reagendamentos.
        $allowance = $this->allowanceFor($sub, $cycle, $service);
        if ($allowance !== null) {
            $used = $this->usedInCycle($cycle, $sub->term('usage_mode') === PlanUsageMode::LimitedPerService->value ? $service->id : null, $ignoreAppointmentId);
            if ($used >= $allowance) {
                throw new BookingException('Você já usou ou reservou todos os atendimentos do plano neste mês.', reason: 'balance');
            }
        }

        $planAppointments = Appointment::query()->where('subscription_id', $sub->id)->where('mode', 'plan')
            ->whereIn('status', AppointmentStatus::occupying())
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId));

        if (($maxFuture = $sub->term('max_future_bookings')) && ! $walkIn) {
            $open = (clone $planAppointments)->where('status', AppointmentStatus::Confirmed->value)->where('starts_at', '>', now())->count();
            if ($open >= $maxFuture) {
                throw new BookingException("Seu plano permite até {$maxFuture} agendamento(s) futuro(s) ao mesmo tempo.", reason: 'max_future');
            }
        }
        $dayStart = $local->startOfDay()->utc();
        if ($maxDay = $sub->term('max_per_day')) {
            if ((clone $planAppointments)->whereBetween('starts_at', [$dayStart, $dayStart->addDay()->subSecond()])->count() >= $maxDay) {
                throw new BookingException("Seu plano permite até {$maxDay} uso(s) por dia.", reason: 'max_day');
            }
        }
        if ($maxWeek = $sub->term('max_per_week')) {
            $weekStart = $local->startOfWeek()->utc();
            if ((clone $planAppointments)->whereBetween('starts_at', [$weekStart, $weekStart->addWeek()->subSecond()])->count() >= $maxWeek) {
                throw new BookingException("Seu plano permite até {$maxWeek} uso(s) por semana.", reason: 'max_week');
            }
        }
        if ($gap = $sub->term('min_days_between_uses')) {
            $near = (clone $planAppointments)
                ->where('starts_at', '>', $local->subDays($gap)->utc())
                ->where('starts_at', '<', $local->addDays($gap)->utc())->exists();
            if ($near) {
                throw new BookingException("Seu plano exige intervalo mínimo de {$gap} dia(s) entre usos.", reason: 'gap');
            }
        }

        return $cycle;
    }

    public function allowanceFor(Subscription $sub, BillingCycle $cycle, Service $service): ?int
    {
        return match ($sub->term('usage_mode')) {
            PlanUsageMode::Unlimited->value => $sub->term('fair_use_limit'),
            PlanUsageMode::LimitedPerService->value => ($u = $sub->term('services', [])[$service->id] ?? null) ? (int) $u : 0,
            default => $cycle->allowance,
        };
    }

    public function usedInCycle(BillingCycle $cycle, ?int $serviceId = null, ?int $ignoreAppointmentId = null): int
    {
        return (int) EntitlementLedgerEntry::query()->where('billing_cycle_id', $cycle->id)
            ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->when($ignoreAppointmentId, fn ($q) => $q->where(fn ($w) => $w->whereNull('appointment_id')->orWhere('appointment_id', '!=', $ignoreAppointmentId)))
            ->selectRaw('COALESCE(SUM(reserved_delta + consumed_delta), 0) as used')->value('used');
    }

    /** @return array{allowance: int|null, reserved: int, consumed: int, available: int|null} */
    public function summary(BillingCycle $cycle): array
    {
        $row = EntitlementLedgerEntry::query()->where('billing_cycle_id', $cycle->id)
            ->selectRaw('COALESCE(SUM(reserved_delta),0) as r, COALESCE(SUM(consumed_delta),0) as c')->first();
        $allowance = $cycle->allowance;

        return [
            'allowance' => $allowance,
            'reserved' => (int) $row->r,
            'consumed' => (int) $row->c,
            'available' => $allowance === null ? null : max(0, $allowance - (int) $row->r - (int) $row->c),
        ];
    }

    // ---- Movimentos do ledger (idempotentes por agendamento + tipo) ----

    public function reserve(Appointment $a): void
    {
        $this->entry($a, 'reserve', 1, 0);
    }

    public function release(Appointment $a, string $note): void
    {
        if ($this->has($a, 'reserve') && ! $this->has($a, 'release') && ! $this->has($a, 'consume') && ! $this->has($a, 'forfeit')) {
            $this->entry($a, 'release', -1, 0, $note);
        }
    }

    public function consume(Appointment $a): void
    {
        if (! $this->has($a, 'reserve')) {
            $this->entry($a, 'reserve', 1, 0, 'Encaixe');
        }
        if (! $this->has($a, 'consume') && ! $this->has($a, 'forfeit') && ! $this->has($a, 'release')) {
            $this->entry($a, 'consume', -1, 1);
        }
    }

    public function forfeit(Appointment $a, string $note): void
    {
        if ($this->has($a, 'reserve') && ! $this->has($a, 'release') && ! $this->has($a, 'consume') && ! $this->has($a, 'forfeit')) {
            $this->entry($a, 'forfeit', -1, 1, $note);
        }
    }

    /** Reagendamento para outro ciclo pago: move os movimentos do agendamento para o novo ciclo. */
    public function moveToCycle(Appointment $a, BillingCycle $cycle): void
    {
        EntitlementLedgerEntry::query()->where('appointment_id', $a->id)->update(['billing_cycle_id' => $cycle->id]);
    }

    private function has(Appointment $a, string $kind): bool
    {
        return EntitlementLedgerEntry::query()->where('appointment_id', $a->id)->where('kind', $kind)->exists();
    }

    private function entry(Appointment $a, string $kind, int $reserved, int $consumed, ?string $note = null): void
    {
        EntitlementLedgerEntry::query()->insertOrIgnore([
            'subscription_id' => $a->subscription_id,
            'billing_cycle_id' => $a->billing_cycle_id,
            'appointment_id' => $a->id,
            'service_id' => $a->service_id,
            'kind' => $kind,
            'reserved_delta' => $reserved,
            'consumed_delta' => $consumed,
            'note' => $note,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
