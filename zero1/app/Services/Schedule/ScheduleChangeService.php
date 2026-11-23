<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleChangeKind;
use App\Enums\ScheduleChangeStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberWorkSchedule;
use App\Models\BlockedInterval;
use App\Models\ScheduleChangeRequest;
use App\Models\ScheduleException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pedidos de alteração de agenda feitos pelo barbeiro e analisados pelo ADM.
 * Normaliza o payload, valida formato na hora do envio e aplica os mesmos registros usados pelas
 * telas atuais de escala (BarberWorkSchedule, ScheduleException, BlockedInterval) ao aprovar.
 */
class ScheduleChangeService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Cria o pedido. O payload deve seguir o formato por tipo, verificado abaixo.
     *
     * - weekly:    ['weekday' => 1..7, 'periods' => [['HH:MM', 'HH:MM'], ...]]
     * - exception: ['date' => 'Y-m-d', 'closed' => bool, 'starts_at' => 'HH:MM', 'ends_at' => 'HH:MM', 'reason' => str]
     * - block:     ['start' => 'Y-m-d H:M', 'end' => 'Y-m-d H:M', 'reason' => str]
     */
    public function submit(Barber $barber, ScheduleChangeKind $kind, array $payload, ?string $reason = null, ?User $actor = null): ScheduleChangeRequest
    {
        $payload = $this->normalize($kind, $payload);
        $req = ScheduleChangeRequest::create([
            'barber_id' => $barber->id,
            'kind' => $kind,
            'payload' => $payload,
            'status' => ScheduleChangeStatus::Pending,
            'reason' => $reason,
        ]);
        $this->audit->log('schedule.change_requested', $req, [], ['barber' => $barber->display_name, 'kind' => $kind->value, 'payload' => $payload]);

        return $req;
    }

    public function cancelByBarber(ScheduleChangeRequest $req, User $actor): ScheduleChangeRequest
    {
        if ($req->status !== ScheduleChangeStatus::Pending) {
            throw new BookingException('Só é possível cancelar pedidos que ainda estão em análise.');
        }
        $req->update(['status' => ScheduleChangeStatus::Cancelled, 'reviewed_at' => now(), 'reviewed_by' => $actor->id]);
        $this->audit->log('schedule.change_cancelled_by_barber', $req);

        return $req;
    }

    /** ADM aprova e aplica. Dentro de uma transação para garantir consistência. */
    public function approve(ScheduleChangeRequest $req, User $actor, ?string $note = null): ScheduleChangeRequest
    {
        $this->guardReviewable($req);
        DB::transaction(function () use ($req, $actor, $note) {
            match ($req->kind) {
                ScheduleChangeKind::Weekly => $this->applyWeekly($req),
                ScheduleChangeKind::Exception => $this->applyException($req, $actor),
                ScheduleChangeKind::Block => $this->applyBlock($req, $actor),
            };
            $req->update(['status' => ScheduleChangeStatus::Approved, 'reviewed_at' => now(), 'reviewed_by' => $actor->id, 'review_note' => $note]);
        });
        $this->audit->log('schedule.change_approved', $req, [], ['note' => $note]);
        $this->notify($req);

        return $req;
    }

    public function reject(ScheduleChangeRequest $req, User $actor, string $note): ScheduleChangeRequest
    {
        $this->guardReviewable($req);
        $req->update(['status' => ScheduleChangeStatus::Rejected, 'reviewed_at' => now(), 'reviewed_by' => $actor->id, 'review_note' => $note]);
        $this->audit->log('schedule.change_rejected', $req, [], ['note' => $note]);
        $this->notify($req);

        return $req;
    }

    private function guardReviewable(ScheduleChangeRequest $req): void
    {
        if ($req->status !== ScheduleChangeStatus::Pending) {
            throw new BookingException('Este pedido já foi analisado.');
        }
    }

    private function normalize(ScheduleChangeKind $kind, array $p): array
    {
        return match ($kind) {
            ScheduleChangeKind::Weekly => $this->normalizeWeekly($p),
            ScheduleChangeKind::Exception => $this->normalizeException($p),
            ScheduleChangeKind::Block => $this->normalizeBlock($p),
        };
    }

    private function normalizeWeekly(array $p): array
    {
        $day = (int) ($p['weekday'] ?? 0);
        if ($day < 1 || $day > 7) {
            throw ValidationException::withMessages(['weekday' => 'Dia da semana inválido.']);
        }
        $periods = collect($p['periods'] ?? [])
            ->map(fn ($x) => [substr($x[0] ?? '', 0, 5), substr($x[1] ?? '', 0, 5)])
            ->filter(fn ($x) => preg_match('/^\d{2}:\d{2}$/', $x[0]) && preg_match('/^\d{2}:\d{2}$/', $x[1]))
            ->sortBy(0)->values();
        for ($i = 0; $i < $periods->count(); $i++) {
            if ($periods[$i][0] >= $periods[$i][1]) {
                throw ValidationException::withMessages(['periods' => 'Início deve ser antes do fim em cada período.']);
            }
            if ($i > 0 && $periods[$i][0] < $periods[$i - 1][1]) {
                throw ValidationException::withMessages(['periods' => 'Os períodos não podem se sobrepor.']);
            }
        }

        return ['weekday' => $day, 'periods' => $periods->map(fn ($x) => [$x[0], $x[1]])->all()];
    }

    private function normalizeException(array $p): array
    {
        $date = $p['date'] ?? '';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < BusinessTime::now()->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Informe uma data futura.']);
        }
        $closed = (bool) ($p['closed'] ?? true);
        $out = ['date' => $date, 'closed' => $closed, 'reason' => trim((string) ($p['reason'] ?? '')) ?: null];
        if (! $closed) {
            foreach (['starts_at', 'ends_at'] as $k) {
                if (! preg_match('/^\d{2}:\d{2}$/', $p[$k] ?? '')) {
                    throw ValidationException::withMessages([$k => 'Informe os horários inicial e final.']);
                }
                $out[$k] = $p[$k];
            }
            if ($out['starts_at'] >= $out['ends_at']) {
                throw ValidationException::withMessages(['ends_at' => 'O fim deve ser depois do início.']);
            }
        }

        return $out;
    }

    private function normalizeBlock(array $p): array
    {
        foreach (['start', 'end'] as $k) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $p[$k] ?? '')) {
                throw ValidationException::withMessages([$k => 'Informe data e hora.']);
            }
        }
        if ($p['end'] <= $p['start']) {
            throw ValidationException::withMessages(['end' => 'O fim deve ser depois do início.']);
        }
        if (CarbonImmutable::parse($p['start'], BusinessTime::tz())->isPast()) {
            throw ValidationException::withMessages(['start' => 'A data inicial não pode ser no passado.']);
        }

        return ['start' => $p['start'], 'end' => $p['end'], 'reason' => trim((string) ($p['reason'] ?? '')) ?: null];
    }

    private function applyWeekly(ScheduleChangeRequest $req): void
    {
        $p = $req->payload;
        BarberWorkSchedule::query()->where('barber_id', $req->barber_id)->where('weekday', $p['weekday'])->delete();
        $now = now();
        $rows = collect($p['periods'])->map(fn ($x) => [
            'barber_id' => $req->barber_id, 'weekday' => $p['weekday'],
            'starts_at' => $x[0].':00', 'ends_at' => $x[1].':00',
            'created_at' => $now, 'updated_at' => $now,
        ])->all();
        if ($rows) {
            BarberWorkSchedule::insert($rows);
        }
    }

    private function applyException(ScheduleChangeRequest $req, User $actor): void
    {
        $p = $req->payload;
        ScheduleException::create([
            'barber_id' => $req->barber_id, 'date' => $p['date'], 'is_closed' => $p['closed'],
            'starts_at' => $p['closed'] ? null : $p['starts_at'],
            'ends_at' => $p['closed'] ? null : $p['ends_at'],
            'reason' => $p['reason'], 'created_by' => $actor->id,
        ]);
    }

    private function applyBlock(ScheduleChangeRequest $req, User $actor): void
    {
        $p = $req->payload;
        $start = CarbonImmutable::parse($p['start'], BusinessTime::tz())->utc();
        $end = CarbonImmutable::parse($p['end'], BusinessTime::tz())->utc();
        BlockedInterval::create([
            'barber_id' => $req->barber_id, 'starts_at' => $start, 'ends_at' => $end,
            'reason' => $p['reason'], 'created_by' => $actor->id,
        ]);
    }

    /** Verifica se há atendimentos confirmados que colidem com o pedido (só aviso para o ADM). */
    public function conflicts(ScheduleChangeRequest $req): array
    {
        $p = $req->payload;
        [$from, $to] = match ($req->kind) {
            ScheduleChangeKind::Weekly => [null, null],
            ScheduleChangeKind::Exception => [
                CarbonImmutable::parse($p['date'], BusinessTime::tz())->startOfDay(),
                CarbonImmutable::parse($p['date'], BusinessTime::tz())->endOfDay(),
            ],
            ScheduleChangeKind::Block => [
                CarbonImmutable::parse($p['start'], BusinessTime::tz()),
                CarbonImmutable::parse($p['end'], BusinessTime::tz()),
            ],
        };
        if (! $from) {
            return [];
        }

        return Appointment::query()->where('barber_id', $req->barber_id)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where('starts_at', '<', $to->utc())->where('occupied_until', '>', $from->utc())
            ->get(['id', 'starts_at', 'ends_at', 'customer_id', 'service_name_snapshot'])
            ->all();
    }

    private function notify(ScheduleChangeRequest $req): void
    {
        $barber = $req->barber?->fresh('user');
        $user = $barber?->user;
        if ($user?->email) {
            rescue(fn () => $user->notify(new \App\Notifications\ScheduleChangeDecision($req)), report: true);
        }
    }
}
