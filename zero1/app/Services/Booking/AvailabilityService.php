<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\BarberStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberWorkSchedule;
use App\Models\BlockedInterval;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Motor de disponibilidade: calcula horários por PROFISSIONAL e por INTERVALO CONTÍNUO.
 * Intervalos semiabertos [início, fim): há conflito quando novo_início < fim_existente E novo_fim > início_existente.
 * O mesmo horário pode ser ocupado por barbeiros diferentes; nunca duas vezes pelo mesmo barbeiro.
 */
class AvailabilityService
{
    public function __construct(private BookingPolicy $policy) {}

    /**
     * Janelas de trabalho do barbeiro na data local (já em UTC).
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function windowsFor(Barber $barber, CarbonImmutable $localDate): array
    {
        $date = $localDate->setTimezone(BusinessTime::tz())->toDateString();

        $shopClosed = ScheduleException::query()->whereNull('barber_id')->whereDate('date', $date)->where('is_closed', true)->exists();
        if ($shopClosed || $barber->status !== BarberStatus::Active) {
            return [];
        }

        $exception = ScheduleException::query()->where('barber_id', $barber->id)->whereDate('date', $date)->latest('id')->first();
        if ($exception) {
            if ($exception->is_closed || ! $exception->starts_at || ! $exception->ends_at) {
                return [];
            }
            $periods = [[$exception->starts_at, $exception->ends_at]];
        } else {
            $weekday = CarbonImmutable::parse($date, BusinessTime::tz())->dayOfWeekIso;
            $periods = BarberWorkSchedule::query()->where('barber_id', $barber->id)->where('weekday', $weekday)
                ->orderBy('starts_at')->get()->map(fn ($s) => [$s->starts_at, $s->ends_at])->all();
        }

        return array_values(array_filter(array_map(function ($p) use ($date) {
            $start = CarbonImmutable::parse("{$date} ".substr($p[0], 0, 5), BusinessTime::tz())->utc();
            $end = CarbonImmutable::parse("{$date} ".substr($p[1], 0, 5), BusinessTime::tz())->utc();

            return $end->gt($start) ? [$start, $end] : null;
        }, $periods)));
    }

    /**
     * Intervalos ocupados do barbeiro (agendamentos ativos + bloqueios individuais e do salão).
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function busyFor(int $barberId, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, ?int $ignoreAppointmentId = null): array
    {
        $busy = Appointment::query()
            ->where('barber_id', $barberId)
            ->whereIn('status', AppointmentStatus::occupying())
            ->where('starts_at', '<', $toUtc)
            ->where('occupied_until', '>', $fromUtc)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->get(['starts_at', 'occupied_until'])
            ->map(fn ($a) => [CarbonImmutable::instance($a->starts_at), CarbonImmutable::instance($a->occupied_until)])
            ->all();

        $blocks = BlockedInterval::query()
            ->where(fn ($q) => $q->where('barber_id', $barberId)->orWhereNull('barber_id'))
            ->where('starts_at', '<', $toUtc)->where('ends_at', '>', $fromUtc)
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($b) => [CarbonImmutable::instance($b->starts_at), CarbonImmutable::instance($b->ends_at)])
            ->all();

        return array_merge($busy, $blocks);
    }

    public static function overlaps(CarbonImmutable $aStart, CarbonImmutable $aEnd, CarbonImmutable $bStart, CarbonImmutable $bEnd): bool
    {
        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }

    /** O intervalo inteiro cabe numa janela de trabalho e não colide com nada do barbeiro? */
    public function isFree(Barber $barber, CarbonImmutable $startUtc, CarbonImmutable $endUtc, ?int $ignoreAppointmentId = null, ?array $busy = null): bool
    {
        $fits = false;
        foreach ($this->windowsFor($barber, $startUtc->setTimezone(BusinessTime::tz())) as [$wStart, $wEnd]) {
            if ($startUtc->gte($wStart) && $endUtc->lte($wEnd)) {
                $fits = true;
                break;
            }
        }
        if (! $fits) {
            return false;
        }

        $busy ??= $this->busyFor($barber->id, $startUtc, $endUtc, $ignoreAppointmentId);
        foreach ($busy as [$bStart, $bEnd]) {
            if (self::overlaps($startUtc, $endUtc, $bStart, $bEnd)) {
                return false;
            }
        }

        return true;
    }

    /** Barbeiros aptos ao serviço (ativos, habilitados, e — se for "qualquer" — participantes do rodízio). */
    public function eligibleBarbers(Service $service, ?array $restrictToIds = null, bool $anyMode = false): Collection
    {
        return Barber::query()->active()
            ->whereHas('services', fn ($q) => $q->where('services.id', $service->id))
            ->when($anyMode, fn ($q) => $q->where('accepts_any_assignment', true))
            ->when($restrictToIds !== null, fn ($q) => $q->whereIn('id', $restrictToIds))
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Horários livres na data, para um barbeiro ou para "qualquer disponível".
     *
     * @param  array{min_start?: CarbonImmutable, max_start?: CarbonImmutable, time_start?: string, time_end?: string, ignore_appointment_id?: int}  $opts
     * @return array<int, array{time: string, start: CarbonImmutable, barber_ids: int[]}>
     */
    public function slots(Service $service, Collection $barbers, CarbonImmutable $localDate, array $opts = []): array
    {
        $tz = BusinessTime::tz();
        $date = $localDate->setTimezone($tz)->startOfDay();
        $duration = $service->effectiveDurationMinutes();
        $step = $this->policy->slotInterval();
        $minStart = $opts['min_start'] ?? null;
        $maxStart = $opts['max_start'] ?? null;

        $result = [];
        foreach ($barbers as $barber) {
            $windows = $this->windowsFor($barber, $date);
            if (! $windows) {
                continue;
            }
            $busy = $this->busyFor($barber->id, $windows[0][0], end($windows)[1], $opts['ignore_appointment_id'] ?? null);

            foreach ($windows as [$wStart, $wEnd]) {
                for ($t = $wStart; $t->addMinutes($duration)->lte($wEnd); $t = $t->addMinutes($step)) {
                    $end = $t->addMinutes($duration);
                    if (($minStart && $t->lt($minStart)) || ($maxStart && $t->gt($maxStart))) {
                        continue;
                    }
                    $local = $t->setTimezone($tz);
                    if (isset($opts['time_start']) && $local->format('H:i') < $opts['time_start']) {
                        continue;
                    }
                    if (isset($opts['time_end']) && $end->setTimezone($tz)->format('H:i') > $opts['time_end']) {
                        continue;
                    }
                    $conflict = false;
                    foreach ($busy as [$bStart, $bEnd]) {
                        if (self::overlaps($t, $end, $bStart, $bEnd)) {
                            $conflict = true;
                            break;
                        }
                    }
                    if (! $conflict) {
                        $key = $local->format('H:i');
                        $result[$key] ??= ['time' => $key, 'start' => $local, 'barber_ids' => []];
                        $result[$key]['barber_ids'][] = $barber->id;
                    }
                }
            }
        }
        ksort($result);

        return array_values($result);
    }
}
