<?php

namespace App\Services\Operations;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\FinancialEntry;
use App\Models\Service;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Club\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Atendimentos executados. Um corte agendado e concluído vira UM atendimento (unique appointment_id),
 * com consumo de franquia, financeiro e comissão vinculados — nunca dois lançamentos.
 */
class VisitService
{
    public function __construct(
        private EntitlementService $entitlements,
        private BookingService $booking,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{price_cents?: int|null, discount_cents?: int, payment_method?: string|null, payment_status?: string, notes?: string|null}  $data
     */
    public function complete(Appointment $a, array $data, ?User $actor): ServiceVisit
    {
        $visit = DB::transaction(function () use ($a, $data, $actor) {
            $a = Appointment::query()->whereKey($a->id)->lockForUpdate()->firstOrFail();
            if ($a->visit()->exists()) {
                throw new BookingException('Este atendimento já foi registrado.');
            }
            if (! $a->status->isOpen()) {
                throw new BookingException('Agendamento '.$a->status->label().' não pode ser concluído.');
            }
            if ($a->starts_at->isFuture() && $a->starts_at->diffInMinutes(now()) > 30) {
                throw new BookingException('Só é possível concluir no dia/horário do atendimento.');
            }

            $isPlan = $a->isPlan();
            $price = $isPlan ? 0 : (int) ($data['price_cents'] ?? $a->price_cents_snapshot ?? 0);
            $discount = $isPlan ? 0 : min($price, (int) ($data['discount_cents'] ?? 0));
            $from = $a->status->value;
            $a->update(['status' => AppointmentStatus::Completed]);
            $this->booking->history($a, $from, AppointmentStatus::Completed->value, $actor?->id, 'Atendimento concluído');

            if ($isPlan) {
                $this->entitlements->consume($a);
            }

            $visit = ServiceVisit::create([
                'appointment_id' => $a->id, 'customer_id' => $a->customer_id, 'barber_id' => $a->barber_id,
                'service_id' => $a->service_id, 'service_name_snapshot' => $a->service_name_snapshot,
                'performed_at' => $a->starts_at, 'mode' => $a->mode,
                'price_cents' => $price, 'discount_cents' => $discount, 'final_cents' => $price - $discount,
                'payment_method' => $isPlan ? 'plano' : ($data['payment_method'] ?? null),
                'payment_status' => $isPlan ? 'paid' : ($data['payment_status'] ?? 'paid'),
                'origin' => $a->origin, 'subscription_id' => $a->subscription_id, 'billing_cycle_id' => $a->billing_cycle_id,
                'notes' => $data['notes'] ?? null, 'created_by' => $actor?->id,
            ]);
            $this->postFinancials($visit, $actor);

            return $visit;
        });
        $this->audit->log('visit.completed', $visit, [], $visit->only(['mode', 'final_cents', 'payment_method', 'barber_id']));

        return $visit;
    }

    /**
     * Atendimento sem agendamento prévio (balcão). Passa pelo MESMO motor de conflitos:
     * ocupa a agenda do barbeiro e, se for de plano, exige ciclo pago e saldo.
     */
    public function walkIn(Customer $customer, Service $service, int $barberId, CarbonImmutable $performedAt, string $mode, array $data, User $actor): ServiceVisit
    {
        $appointment = $this->booking->book(new BookingRequest(
            customer: $customer, service: $service, barberId: $barberId, start: $performedAt, mode: $mode,
            origin: $data['origin'] ?? 'balcao', actor: $actor, notes: $data['notes'] ?? null, staff: true, walkIn: true,
        ));

        return $this->complete($appointment, $data, $actor);
    }

    public function postFinancials(ServiceVisit $visit, ?User $actor): void
    {
        // Corte coberto por plano não gera nova receita de caixa (a receita é a mensalidade).
        if ($visit->mode === 'avulso' && $visit->final_cents > 0 && $visit->payment_method !== 'cortesia') {
            FinancialEntry::query()->firstOrCreate(
                ['source_type' => $visit->getMorphClass(), 'source_id' => $visit->id, 'type' => 'income'],
                ['category' => 'avulso', 'amount_cents' => $visit->final_cents, 'occurred_at' => $visit->performed_at,
                    'payment_method' => $visit->payment_method, 'status' => $visit->payment_status,
                    'description' => $visit->service_name_snapshot.' — '.$visit->barber->display_name, 'created_by' => $actor?->id]
            );
        }

        $rule = CommissionRule::query()
            ->where(fn ($q) => $q->where('barber_id', $visit->barber_id)->orWhereNull('barber_id'))
            ->where(fn ($q) => $q->where('service_id', $visit->service_id)->orWhereNull('service_id'))
            ->orderByRaw('CASE WHEN barber_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN service_id IS NULL THEN 1 ELSE 0 END')
            ->first();
        if ($rule) {
            $amount = $visit->mode === 'plan'
                ? (int) $rule->plan_visit_cents
                : (int) round($visit->final_cents * $rule->percent / 100);
            if ($amount > 0) {
                CommissionLedgerEntry::query()->firstOrCreate(['service_visit_id' => $visit->id], [
                    'barber_id' => $visit->barber_id, 'amount_cents' => $amount,
                    'basis' => $visit->mode === 'plan' ? 'Fixo por atendimento de plano' : $rule->percent.'% de '.$visit->final_cents.' centavos',
                ]);
            }
        }
    }

    /** Marca um avulso pendente como pago. */
    public function markPaid(ServiceVisit $visit, string $method): void
    {
        DB::transaction(function () use ($visit, $method) {
            $visit->update(['payment_status' => 'paid', 'payment_method' => $method]);
            FinancialEntry::query()->where('source_type', $visit->getMorphClass())->where('source_id', $visit->id)
                ->update(['status' => 'paid', 'payment_method' => $method]);
        });
    }
}
