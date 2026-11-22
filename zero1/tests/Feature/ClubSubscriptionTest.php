<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CycleStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Models\EntitlementLedgerEntry;
use App\Models\FinancialEntry;
use App\Models\PaymentWebhookEvent;
use App\Services\Booking\BookingService;
use App\Services\Club\EntitlementService;
use App\Services\Club\SubscriptionService;
use App\Services\Payments\GatewayPayment;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class ClubSubscriptionTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    private $corte;

    private $barberX;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->freezeAt();
        $this->corte = $this->service('Corte', 30);
        $this->barberX = $this->barber('X', [$this->corte]);
    }

    private function assertPlanBookingRefused($customer, string $when, ?string $reason = null): void
    {
        try {
            $this->book($customer, $this->corte, $this->barberX, $when, 'plan');
            $this->fail('Agendamento pelo plano deveria ser recusado');
        } catch (BookingException $e) {
            if ($reason) {
                $this->assertSame($reason, $e->reason, $e->getMessage());
            }
        }
    }

    /** Aceitação 1 e 2 */
    public function test_pending_or_authorized_but_unpaid_subscription_grants_nothing(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]), pay: false);
        $this->assertSame(SubscriptionStatus::AwaitingPayment, $sub->status);
        $this->assertPlanBookingRefused($c, '2026-03-05 10:00', 'unpaid');

        // Gateway "autoriza" a assinatura, mas o pagamento do ciclo é recusado → continua sem direito.
        $this->gateway()->simulateCheckout($sub->gateway_subscription_id, $sub->cycles()->first()->externalReference(), GatewayPayment::REJECTED);
        $this->assertSame(CycleStatus::Failed, $sub->cycles()->first()->status);
        $this->assertPlanBookingRefused($c, '2026-03-05 10:00', 'unpaid');

        // Avulso continua possível (não usa saldo do plano).
        $a = $this->book($c, $this->corte, $this->barberX, '2026-03-05 10:00');
        $this->assertSame('avulso', $a->mode);
        $this->assertSame(0, EntitlementLedgerEntry::count());
    }

    /** Aceitação 3 */
    public function test_duplicate_webhook_creates_one_paid_cycle_and_one_financial_entry(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]), pay: false);
        $ref = $sub->cycles()->first()->externalReference();
        $payment = $this->gateway()->simulateCheckout($sub->gateway_subscription_id, $ref, GatewayPayment::APPROVED);
        $event = PaymentWebhookEvent::first();

        $this->gateway()->redeliver($event->event_id, 'payment', $payment->external_id); // mesmo evento
        $this->gateway()->redeliver('evt_outro_id', 'payment', $payment->external_id);   // evento novo, mesmo pagamento

        $this->assertSame(SubscriptionStatus::Active, $sub->fresh()->status);
        $this->assertSame(1, $sub->cycles()->where('status', 'paid')->count());
        $this->assertSame(1, FinancialEntry::where('category', 'assinatura')->count());
        $this->assertSame(2, PaymentWebhookEvent::count());
        $this->assertSame(4, app(EntitlementService::class)->summary($sub->cycles()->first())['available'], 'Saldo não duplica');

        // HTTP: assinatura inválida é rejeitada.
        $this->postJson('/webhooks/pagamentos/simulated', ['id' => 'x', 'type' => 'payment', 'data' => ['id' => $payment->external_id]], ['X-Signature' => 'falsa'])->assertStatus(401);
    }

    /** Aceitação 4 e 17 (troca de mês) */
    public function test_failed_renewal_blocks_only_the_unpaid_cycle(): void
    {
        $this->freezeAt('2026-01-31 08:00');
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte], ['uses_per_cycle' => 10]));
        $cycle1 = $sub->cycles()->first();
        $this->assertSame('2026-01-31', $cycle1->period_start->toDateString());
        $this->assertSame('2026-02-28', $cycle1->period_end->toDateString(), '31/01 + 1 mês = 28/02 (sem pular)');

        $inPaid = $this->book($c, $this->corte, $this->barberX, '2026-02-27 10:00', 'plan');
        $this->assertSame($cycle1->id, $inPaid->billing_cycle_id);
        // 28/02 já pertence ao ciclo seguinte, ainda não pago.
        $this->assertPlanBookingRefused($c, '2026-02-28 10:00', 'unpaid_cycle');

        config(['zero1.payments.simulated.card_renewal_outcome' => 'rejected']);
        $this->freezeAt('2026-02-28 07:00');
        app(SubscriptionService::class)->tick();
        $sub->refresh();
        $this->assertSame(CycleStatus::Failed, $sub->latestCycle()->status);
        $this->assertSame(SubscriptionStatus::PastDue, $sub->status);
        $this->assertPlanBookingRefused($c, '2026-03-02 10:00', 'unpaid');
        $this->assertSame(AppointmentStatus::Confirmed, $inPaid->fresh()->status, 'O que estava no ciclo pago segue válido');

        // Regularização restaura o direito somente após confirmação.
        $this->gateway()->simulateCheckout($sub->gateway_subscription_id, $sub->latestCycle()->externalReference(), GatewayPayment::APPROVED);
        $this->assertSame(SubscriptionStatus::Active, $sub->fresh()->status);
        $this->book($c, $this->corte, $this->barberX, '2026-03-02 10:00', 'plan');
    }

    /** Aceitação 5 */
    public function test_cancelled_card_and_unpaid_pix_do_not_create_covered_cuts(): void
    {
        $pixCustomer = $this->customer();
        $pix = $this->subscribe($pixCustomer, $this->plan([$this->corte]), pay: false, method: 'pix');
        $this->assertPlanBookingRefused($pixCustomer, '2026-03-05 10:00');
        $this->assertFalse(app(PaymentGatewayManager::class)->driver()->supportsAutomaticPix(), 'Sem Pix automático comprovado');

        $cardCustomer = $this->customer();
        $card = $this->subscribe($cardCustomer, $this->plan([$this->corte]), pay: false);
        app(SubscriptionService::class)->requestCancellation($card, null, 'Desistiu');
        $this->assertSame(SubscriptionStatus::Cancelled, $card->fresh()->status);
        $this->assertPlanBookingRefused($cardCustomer, '2026-03-05 10:00');
    }

    /** Aceitação 11 */
    public function test_franchise_counts_reservations_and_uses_and_cancellation_in_time_restores(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte], ['uses_per_cycle' => 2]));
        $cycle = $sub->currentPaidCycle();
        $svc = app(EntitlementService::class);

        $a1 = $this->book($c, $this->corte, $this->barberX, '2026-03-05 10:00', 'plan');
        $a2 = $this->book($c, $this->corte, $this->barberX, '2026-03-12 10:00', 'plan');
        $this->assertSame(['allowance' => 2, 'reserved' => 2, 'consumed' => 0, 'available' => 0], $svc->summary($cycle));
        $this->assertPlanBookingRefused($c, '2026-03-19 10:00', 'balance');

        app(BookingService::class)->cancel($a2, false, null); // no prazo → devolve
        $this->assertSame(1, $svc->summary($cycle)['available']);

        $a3 = $this->book($c, $this->corte, $this->barberX, '2026-03-19 10:00', 'plan');
        $this->freezeAt('2026-03-19 09:30'); // cancelamento fora do prazo (2h) → perde o crédito
        app(BookingService::class)->cancel($a3, false, null);
        $this->assertSame(['allowance' => 2, 'reserved' => 1, 'consumed' => 1, 'available' => 0], $svc->summary($cycle));

        // Ledger: nenhum contador sobrescrito, só movimentos.
        $this->assertSame(['reserve', 'reserve', 'release', 'reserve', 'forfeit'], EntitlementLedgerEntry::orderBy('id')->pluck('kind')->all());
    }

    public function test_plan_rules_are_frozen_at_signup(): void
    {
        $c = $this->customer();
        $plan = $this->plan([$this->corte], ['uses_per_cycle' => 1]);
        $sub = $this->subscribe($c, $plan);
        $plan->update(['uses_per_cycle' => 10, 'price_cents' => 1]);

        $this->assertSame(9900, $sub->fresh()->price_cents);
        $this->book($c, $this->corte, $this->barberX, '2026-03-05 10:00', 'plan');
        $this->assertPlanBookingRefused($c, '2026-03-12 10:00', 'balance');
    }

    public function test_plan_limits_are_enforced(): void
    {
        $c = $this->customer();
        $this->subscribe($c, $this->plan([$this->corte], ['uses_per_cycle' => 10, 'max_future_bookings' => 1, 'min_days_between_uses' => 3, 'allowed_weekdays' => [1, 2, 3, 4, 5]]));
        $this->assertPlanBookingRefused($c, '2026-03-07 10:00', 'weekday'); // sábado
        $this->book($c, $this->corte, $this->barberX, '2026-03-05 10:00', 'plan');
        $this->assertPlanBookingRefused($c, '2026-03-13 10:00', 'max_future');
        $other = $this->service('Barba', 30);
        $this->assertPlanBookingRefused($c, '2026-03-05 11:00'); // máx futuros / intervalo
        try {
            $this->book($c, $other, $this->barberX, '2026-03-10 10:00', 'plan');
            $this->fail();
        } catch (BookingException $e) {
            $this->assertContains($e->reason, ['not_covered', 'no_barber']);
        }
    }

    /** Aceitação 16 */
    public function test_reconciliation_recovers_lost_webhook(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]), pay: false);
        $this->gateway()->simulateCheckout($sub->gateway_subscription_id, $sub->cycles()->first()->externalReference(), GatewayPayment::APPROVED, loseWebhook: true);

        $this->assertSame(SubscriptionStatus::AwaitingPayment, $sub->fresh()->status, 'Retorno/checkout não confirma sozinho');
        $this->artisan('zero1:reconcile-payments')->assertSuccessful();
        $this->assertSame(SubscriptionStatus::Active, $sub->fresh()->status);
        $this->assertSame(CycleStatus::Paid, $sub->cycles()->first()->status);
    }

    /** Aceitação 18 */
    public function test_cancellation_keeps_paid_period_and_stops_renewals(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]));
        app(SubscriptionService::class)->requestCancellation($sub, null, 'Mudança de cidade');
        $sub->refresh();
        $this->assertSame(SubscriptionStatus::CancelRequested, $sub->status);
        $this->assertSame('2026-04-02', $sub->cancel_effective_on->toDateString());
        $this->assertSame('cancelled', $this->gateway()->getSubscription($sub->gateway_subscription_id)->status);

        $this->book($c, $this->corte, $this->barberX, '2026-03-20 10:00', 'plan'); // período pago segue valendo

        $this->freezeAt('2026-04-02 07:00');
        app(SubscriptionService::class)->tick();
        $this->assertSame(SubscriptionStatus::Cancelled, $sub->fresh()->status);
        $this->assertSame(1, $sub->cycles()->count(), 'Nenhuma renovação criada');
        $this->assertPlanBookingRefused($c, '2026-04-06 10:00');
    }

    public function test_refund_and_chargeback_reverse_rights_and_flag_future_appointments(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]));
        $a = $this->book($c, $this->corte, $this->barberX, '2026-03-10 10:00', 'plan');
        $cycle = $sub->cycles()->first();

        $this->gateway()->simulateStatusChange($cycle->gateway_payment_id, GatewayPayment::CHARGED_BACK);
        $sub->refresh();
        $this->assertSame(CycleStatus::ChargedBack, $cycle->fresh()->status);
        $this->assertTrue($sub->fraud_blocked);
        $this->assertTrue($a->fresh()->plan_payment_issue, 'Agendamento futuro não é presumido válido');
        $this->assertSame(1, FinancialEntry::where('type', 'refund')->count());
        $this->assertPlanBookingRefused($c, '2026-03-12 10:00');

        // Evento antigo de "aprovado" chegando depois não reativa (fora de ordem + bloqueio).
        $this->gateway()->redeliver('evt_atrasado', 'payment', $cycle->gateway_payment_id);
        $this->assertSame(CycleStatus::ChargedBack, $cycle->fresh()->status);
    }

    public function test_out_of_order_rejection_does_not_undo_payment(): void
    {
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]));
        $cycle = $sub->cycles()->first();
        $res = app(SubscriptionService::class)->applyPayment(new GatewayPayment('sim_pay_old', GatewayPayment::REJECTED, 9900, $cycle->externalReference()));
        $this->assertSame('ignored_paid_cycle', $res);
        $this->assertSame(CycleStatus::Paid, $cycle->fresh()->status);
    }

    public function test_unpaid_policy_cancel_cancels_affected_appointments(): void
    {
        SiteSettings::class;
        app(SiteSettings::class)->set(['booking.unpaid_policy' => 'cancel']);
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]));
        $a = $this->book($c, $this->corte, $this->barberX, '2026-03-10 10:00', 'plan');
        $this->gateway()->simulateStatusChange($sub->cycles()->first()->gateway_payment_id, GatewayPayment::REFUNDED);
        $this->assertSame(AppointmentStatus::CancelledByShop, $a->fresh()->status);
        $this->assertSame(SubscriptionStatus::Refunded, $sub->fresh()->status);
    }
}
