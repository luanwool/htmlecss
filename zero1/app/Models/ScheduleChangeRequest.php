<?php

namespace App\Models;

use App\Enums\ScheduleChangeKind;
use App\Enums\ScheduleChangeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleChangeRequest extends Model
{
    protected $fillable = ['barber_id', 'kind', 'payload', 'status', 'reason', 'review_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'kind' => ScheduleChangeKind::class,
            'status' => ScheduleChangeStatus::class,
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', ScheduleChangeStatus::Pending->value);
    }

    /** Resumo legível do pedido, para mostrar em listas e e-mails. */
    public function summary(): string
    {
        $p = $this->payload;

        return match ($this->kind) {
            ScheduleChangeKind::Weekly => $this->weeklySummary($p),
            ScheduleChangeKind::Exception => \App\Support\BusinessTime::weekdayName(\Carbon\Carbon::parse($p['date'])->dayOfWeekIso)
                .' '.\Carbon\Carbon::parse($p['date'])->format('d/m/Y').' — '
                .($p['closed'] ? 'folga' : 'atender das '.substr($p['starts_at'], 0, 5).' às '.substr($p['ends_at'], 0, 5))
                .($p['reason'] ?? '' ? ' · '.$p['reason'] : ''),
            ScheduleChangeKind::Block => 'Bloqueio '.\Carbon\Carbon::parse($p['start'])->format('d/m H:i')
                .' até '.\Carbon\Carbon::parse($p['end'])->format('d/m H:i')
                .($p['reason'] ?? '' ? ' · '.$p['reason'] : ''),
        };
    }

    private function weeklySummary(array $p): string
    {
        $dayName = \App\Support\BusinessTime::weekdayName((int) $p['weekday']);
        $periods = collect($p['periods'] ?? [])->map(fn ($x) => $x[0].'–'.$x[1])->implode(', ');

        return "Escala de {$dayName}: ".($periods ?: 'sem atendimento');
    }
}
