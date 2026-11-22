<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Barber;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Club\EntitlementService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ÚNICO ponto de criação/alteração de agendamentos. Todos os canais passam por aqui:
 * transação + trava por barbeiro/dia (SELECT … FOR UPDATE) + revalidação completa dentro da trava.
 */
class BookingService
{
    /** Tentativas em caso de deadlock/serialização (o Laravel repete a transação inteira). */
    private const ATTEMPTS = 3;

    public function __construct(
        private AvailabilityService $availability,
        private BookingPolicy $policy,
        private EntitlementService $entitlements,
        private AuditLogger $audit,
    ) {}

    public function book(BookingRequest $r): Appointment
    {
        $service = $r->service;
        $start = $r->start->utc()->startOfMinute();
        $end = $start->addMinutes($service->duration_minutes);
        $occupiedUntil = $start->addMinutes($service->effectiveDurationMinutes());

        $this->assertServiceBookable($r);
        $this->assertTiming($r, $start);

        $subscription = null;
        if ($r->mode === 'plan') {
            $subscription = $this->entitlements->usableSubscription($r->customer);
            if (! $subscription) {
                throw new BookingException('Você não possui assinatura ativa do Clube Zero 1.', reason: 'no_subscription');
            }
        }

        $candidates = $this->candidates($service, $r->barberId, $subscription);
        if ($candidates->isEmpty()) {
            throw new BookingException('Nenhum profissional disponível realiza este serviço.', reason: 'no_barber');
        }

        $this->ensureLockRows($candidates->pluck('id')->all(), [$start]);
        $appointment = DB::transaction(function () use ($r, $service, $start, $end, $occupiedUntil, $candidates, $subscription) {
            $this->lockDays($candidates->pluck('id')->all(), [$start]);

            if (! $r->staff) {
                $this->assertCustomerLimits($r, $start);
            }

            $free = $candidates->filter(fn (Barber $b) => $this->availability->isFree($b, $start, $occupiedUntil));
            if ($free->isEmpty()) {
                throw new BookingException(
                    $r->barberId ? 'Este horário acabou de ser ocupado. Escolha outro horário.' : 'Não há profissional livre neste horário.',
                    $this->alternatives($service, $candidates, $start),
                    'slot_taken',
                );
            }
            $barber = $this->choose($free, $start);

            $cycle = null;
            if ($subscription) {
                // Trava a assinatura: duas reservas simultâneas não consomem o mesmo saldo.
                $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->first();
                $cycle = $this->entitlements->assertCanUse($subscription, $service, $start, $barber->id, walkIn: $r->walkIn || $r->staff);
            }

            $appointment = Appointment::create([
                'uid' => (string) Str::uuid(),
                'customer_id' => $r->customer->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'occupied_until' => $occupiedUntil,
                'duration_minutes_snapshot' => $service->duration_minutes,
                'buffer_minutes_snapshot' => $service->buffer_minutes,
                'service_name_snapshot' => $service->name,
                'price_cents_snapshot' => $r->mode === 'plan' ? null : $service->price_cents,
                'status' => AppointmentStatus::Confirmed,
                'mode' => $r->mode,
                'origin' => $r->origin,
                'subscription_id' => $subscription?->id,
                'billing_cycle_id' => $cycle?->id,
                'notes' => $r->notes,
                'ics_sequence' => 0,
                'plan_payment_issue' => false,
                'created_by' => $r->actor?->id,
            ]);
            $this->history($appointment, null, AppointmentStatus::Confirmed->value, $r->actor?->id, 'Criado via '.$r->origin);
            if ($subscription) {
                $this->entitlements->reserve($appointment);
            }

            return $appointment;
        }, self::ATTEMPTS);

        $this->audit->log('appointment.created', $appointment, [], $appointment->only(['barber_id', 'service_id', 'starts_at', 'mode', 'origin']));
        DB::afterCommit(fn () => $this->notify($appointment, 'confirmed'));

        return $appointment;
    }

    public function reschedule(Appointment $a, CarbonImmutable $newStart, ?int $newBarberId, ?User $actor, bool $staff = false): Appointment
    {
        if (! $a->status->isOpen()) {
            throw new BookingException('Somente agendamentos confirmados podem ser reagendados.');
        }
        if (! $staff) {
            $this->assertCustomerCanChange($a);
        }
        $service = $a->service;
        $start = $newStart->utc()->startOfMinute();
        $occupiedUntil = $start->addMinutes($a->duration_minutes_snapshot + $a->buffer_minutes_snapshot);
        $this->assertTiming(new BookingRequest($a->customer, $service, $newBarberId, $start, $a->mode, staff: $staff), $start);

        $subscription = $a->subscription;
        $candidates = $this->candidates($service, $newBarberId ?? $a->barber_id, $a->mode === 'plan' ? $subscription : null);
        if ($candidates->isEmpty()) {
            throw new BookingException('Profissional indisponível para este serviço.');
        }

        $old = $a->only(['starts_at', 'ends_at', 'barber_id']);
        $this->ensureLockRows(array_unique(array_merge($candidates->pluck('id')->all(), [$a->barber_id])), [$start, CarbonImmutable::instance($a->starts_at)]);
        DB::transaction(function () use ($a, $start, $occupiedUntil, $candidates, $subscription, $service, $staff) {
            $this->lockDays(array_unique(array_merge($candidates->pluck('id')->all(), [$a->barber_id])), [$start, CarbonImmutable::instance($a->starts_at)]);
            $a->refresh();
            if (! $a->status->isOpen()) {
                throw new BookingException('O agendamento mudou de situação. Atualize a página.');
            }
            $free = $candidates->filter(fn (Barber $b) => $this->availability->isFree($b, $start, $occupiedUntil, $a->id));
            if ($free->isEmpty()) {
                throw new BookingException('Este horário não está disponível.', $this->alternatives($service, $candidates, $start, $a->id), 'slot_taken');
            }
            $barber = $free->first();

            if ($a->mode === 'plan') {
                $sub = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->first();
                $cycle = $this->entitlements->assertCanUse($sub, $service, $start, $barber->id, $a->id, $staff);
                if ($cycle->id !== $a->billing_cycle_id) {
                    $a->billing_cycle_id = $cycle->id;
                    $this->entitlements->moveToCycle($a, $cycle);
                }
            }

            $a->fill([
                'barber_id' => $barber->id,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($a->duration_minutes_snapshot),
                'occupied_until' => $occupiedUntil,
                'ics_sequence' => (int) $a->ics_sequence + 1,
                'reminder_24h_sent_at' => null,
                'reminder_2h_sent_at' => null,
                'plan_payment_issue' => false,
            ])->save();
        }, self::ATTEMPTS);

        $this->history($a, $a->status->value, $a->status->value, $actor?->id, 'Reagendado', ['de' => $old]);
        $this->audit->log('appointment.rescheduled', $a, $old, $a->only(['starts_at', 'ends_at', 'barber_id']));
        $this->notify($a->fresh(), 'rescheduled');

        return $a;
    }

    public function cancel(Appointment $a, bool $byShop, ?User $actor, ?string $reason = null): Appointment
    {
        if (! $a->status->isOpen()) {
            throw new BookingException('Este agendamento não pode mais ser cancelado.');
        }
        $late = CarbonImmutable::instance($a->starts_at)->lt(now()->addHours($this->policy->cancelMinHours()));
        if (! $byShop) {
            $this->assertCustomerCanChange($a, allowLate: true);
        }

        DB::transaction(function () use ($a, $byShop, $actor, $reason, $late) {
            $from = $a->status->value;
            $a->update([
                'status' => $byShop ? AppointmentStatus::CancelledByShop : AppointmentStatus::CancelledByCustomer,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'ics_sequence' => (int) $a->ics_sequence + 1,
            ]);
            if ($a->isPlan()) {
                if (! $byShop && $late && $this->policy->lateCancelForfeits()) {
                    $this->entitlements->forfeit($a, 'Cancelamento fora do prazo');
                } else {
                    $this->entitlements->release($a, $byShop ? 'Cancelado pela barbearia' : 'Cancelado no prazo');
                }
            }
            $this->history($a, $from, $a->status->value, $actor?->id, $reason);
        });
        $this->audit->log('appointment.cancelled', $a, [], ['status' => $a->status->value, 'reason' => $reason]);
        $this->notify($a, 'cancelled');

        return $a;
    }

    public function markNoShow(Appointment $a, ?User $actor): Appointment
    {
        if (! $a->status->isOpen() || CarbonImmutable::instance($a->starts_at)->isFuture()) {
            throw new BookingException('Só é possível marcar ausência depois do horário marcado.');
        }
        DB::transaction(function () use ($a, $actor) {
            $from = $a->status->value;
            $a->update(['status' => AppointmentStatus::NoShow]);
            if ($a->isPlan()) {
                $this->policy->noShowConsumesCredit()
                    ? $this->entitlements->forfeit($a, 'Não compareceu')
                    : $this->entitlements->release($a, 'Não compareceu (política não consome)');
            }
            $this->history($a, $from, AppointmentStatus::NoShow->value, $actor?->id);
        });
        $this->audit->log('appointment.no_show', $a);

        return $a;
    }

    public function start(Appointment $a, ?User $actor): Appointment
    {
        if ($a->status !== AppointmentStatus::Confirmed) {
            throw new BookingException('Atendimento não pode ser iniciado.');
        }
        $a->update(['status' => AppointmentStatus::InProgress]);
        $this->history($a, AppointmentStatus::Confirmed->value, AppointmentStatus::InProgress->value, $actor?->id);

        return $a;
    }

    /** Horários disponíveis para exibir (mesmas regras que a reserva). */
    public function availableSlots(Service $service, ?int $barberId, CarbonImmutable $localDate, ?Subscription $planSub = null, bool $staff = false, ?int $ignoreAppointmentId = null): array
    {
        $candidates = $this->candidates($service, $barberId, $planSub);
        $minAdvance = $staff ? 0 : max($this->policy->minAdvanceMinutes(), (int) $service->min_advance_minutes, (int) $planSub?->term('min_advance_minutes'));
        $maxDays = $staff ? 365 : min($this->policy->maxAdvanceDays(), $planSub?->term('max_advance_days') ?: PHP_INT_MAX);
        $opts = ['min_start' => now()->toImmutable()->addMinutes($minAdvance), 'max_start' => now()->toImmutable()->addDays($maxDays), 'ignore_appointment_id' => $ignoreAppointmentId];
        if ($planSub && ($ts = $planSub->term('allowed_time_start')) && ($te = $planSub->term('allowed_time_end'))) {
            $opts['time_start'] = substr($ts, 0, 5);
            $opts['time_end'] = substr($te, 0, 5);
        }
        if ($planSub && ($days = $planSub->term('allowed_weekdays')) && ! in_array($localDate->dayOfWeekIso, $days)) {
            return [];
        }

        return $this->availability->slots($service, $candidates, $localDate, $opts);
    }

    // ------------------------------------------------------------------

    /** Barbeiros aptos: específico (precisa estar ativo e habilitado) ou "qualquer" (participantes do rodízio). */
    public function candidates(Service $service, ?int $barberId, ?Subscription $planSub): Collection
    {
        $restrict = null;
        if ($planSub && ! $planSub->term('all_barbers', true)) {
            $restrict = $planSub->term('barber_ids', []);
        }
        if ($barberId) {
            $restrict = $restrict === null ? [$barberId] : array_values(array_intersect($restrict, [$barberId]));
        }

        return $this->availability->eligibleBarbers($service, $restrict, anyMode: $barberId === null);
    }

    /**
     * Garante que as linhas de trava existam ANTES da transação (autocommit). Inserir dentro da transação
     * concorrente gera deadlock de "insert intention" no InnoDB.
     */
    private function ensureLockRows(array $barberIds, array $starts): void
    {
        $rows = [];
        foreach ($barberIds as $id) {
            foreach ($this->localDates($starts) as $d) {
                $rows[] = ['barber_id' => $id, 'date' => $d];
            }
        }
        DB::table('barber_day_locks')->insertOrIgnore($rows);
    }

    /** Trava as linhas (barbeiro, dia) em ordem fixa — serializa reservas concorrentes do mesmo barbeiro/dia. */
    private function lockDays(array $barberIds, array $starts): void
    {
        sort($barberIds);
        DB::table('barber_day_locks')->whereIn('barber_id', $barberIds)->whereIn('date', $this->localDates($starts))
            ->orderBy('barber_id')->orderBy('date')->lockForUpdate()->get();
    }

    private function localDates(array $starts): array
    {
        return collect($starts)->map(fn ($s) => $s->setTimezone(BusinessTime::tz())->toDateString())->unique()->sort()->values()->all();
    }

    private function choose(Collection $free, CarbonImmutable $start): Barber
    {
        if ($free->count() === 1 || $this->policy->assignmentPolicy() === 'first') {
            return $free->first();
        }
        $day = $start->setTimezone(BusinessTime::tz())->startOfDay()->utc();
        $load = Appointment::query()->whereIn('barber_id', $free->pluck('id'))->occupying()
            ->whereBetween('starts_at', [$day, $day->addDay()])
            ->selectRaw('barber_id, COUNT(*) as n')->groupBy('barber_id')->pluck('n', 'barber_id');

        return $free->sortBy(fn (Barber $b) => [(int) ($load[$b->id] ?? 0), $b->sort_order, $b->id])->first();
    }

    private function assertServiceBookable(BookingRequest $r): void
    {
        $s = $r->service;
        if (! $s->is_active || $s->trashed()) {
            throw new BookingException('Serviço indisponível.');
        }
        if ($r->mode === 'avulso' && ! $s->is_bookable_standalone && ! $r->staff) {
            throw new BookingException('Este serviço é exclusivo para assinantes do Clube.');
        }
    }

    private function assertTiming(BookingRequest $r, CarbonImmutable $start): void
    {
        $now = now()->toImmutable();
        if ($r->walkIn) {
            if ($start->lt($now->subHours($this->policy->walkInPastHours())) || $start->gt($now->addHours(12))) {
                throw new BookingException('Horário fora da janela permitida para lançamentos de balcão.');
            }

            return;
        }
        if ($start->lte($now)) {
            throw new BookingException('Não é possível agendar no passado.', reason: 'past');
        }
        if ($r->staff) {
            return;
        }
        $minAdvance = max($this->policy->minAdvanceMinutes(), (int) $r->service->min_advance_minutes);
        if ($start->lt($now->addMinutes($minAdvance))) {
            throw new BookingException("Agende com pelo menos {$minAdvance} minutos de antecedência.", reason: 'advance');
        }
        if ($start->gt($now->addDays($this->policy->maxAdvanceDays()))) {
            throw new BookingException('Agenda aberta somente para os próximos '.$this->policy->maxAdvanceDays().' dias.', reason: 'advance');
        }
    }

    private function assertCustomerLimits(BookingRequest $r, CarbonImmutable $start): void
    {
        $open = Appointment::query()->where('customer_id', $r->customer->id)
            ->where('status', AppointmentStatus::Confirmed->value)->where('starts_at', '>', now())->count();
        if ($open >= $this->policy->maxOpenPerCustomer()) {
            throw new BookingException('Você atingiu o limite de agendamentos futuros. Conclua ou cancele um deles.', reason: 'customer_limit');
        }
        $occupiedUntil = $start->addMinutes($r->service->effectiveDurationMinutes());
        $overlap = Appointment::query()->where('customer_id', $r->customer->id)->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::InProgress->value])
            ->where('starts_at', '<', $occupiedUntil)->where('occupied_until', '>', $start)->exists();
        if ($overlap) {
            throw new BookingException('Você já tem um agendamento nesse horário.', reason: 'customer_overlap');
        }
    }

    private function assertCustomerCanChange(Appointment $a, bool $allowLate = false): void
    {
        $limit = now()->addHours($this->policy->cancelMinHours());
        if (! $allowLate && CarbonImmutable::instance($a->starts_at)->lt($limit)) {
            throw new BookingException('Alterações só são permitidas até '.$this->policy->cancelMinHours().'h antes do horário. Fale com a barbearia.', reason: 'late');
        }
        if (CarbonImmutable::instance($a->starts_at)->isPast()) {
            throw new BookingException('Este horário já passou.');
        }
    }

    /** Até 6 horários alternativos no mesmo dia, para o caso de o slot ter sido ocupado. */
    private function alternatives(Service $service, Collection $candidates, CarbonImmutable $start, ?int $ignore = null): array
    {
        $slots = $this->availability->slots($service, $candidates, $start->setTimezone(BusinessTime::tz()), [
            'min_start' => now()->toImmutable()->addMinutes($this->policy->minAdvanceMinutes()), 'ignore_appointment_id' => $ignore,
        ]);

        return collect($slots)->sortBy(fn ($s) => abs($s['start']->diffInMinutes($start)))->take(6)
            ->sortBy('time')->map(fn ($s) => ['time' => $s['time'], 'barber_id' => count($s['barber_ids']) === 1 ? $s['barber_ids'][0] : null])
            ->values()->all();
    }

    public function history(Appointment $a, ?string $from, string $to, ?int $userId, ?string $note = null, ?array $changes = null): void
    {
        AppointmentStatusHistory::create([
            'appointment_id' => $a->id, 'from_status' => $from, 'to_status' => $to,
            'note' => $note ? mb_substr($note, 0, 255) : null, 'changes' => $changes, 'user_id' => $userId,
        ]);
    }

    private function notify(Appointment $a, string $event): void
    {
        $customer = $a->customer;
        if ($customer?->email) {
            rescue(fn () => $customer->notify(new AppointmentNotification($a, $event)), report: true);
        }
    }
}
