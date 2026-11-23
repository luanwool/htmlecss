<?php

namespace App\Models;

use App\Enums\PlanUsageMode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory, SoftDeletes;

    /** Campos cuja alteração muda as condições comerciais (incrementa version). */
    public const COMMERCIAL_FIELDS = [
        'price_cents', 'usage_mode', 'uses_per_cycle', 'max_future_bookings', 'max_per_day', 'max_per_week',
        'min_days_between_uses', 'min_advance_minutes', 'max_advance_days', 'allowed_weekdays',
        'allowed_time_start', 'allowed_time_end', 'all_barbers', 'min_commitment_months', 'grace_days',
        'guest_policy', 'no_show_policy', 'cancellation_policy', 'benefits', 'terms',
    ];

    protected $attributes = ['version' => 1, 'all_barbers' => true, 'currency' => 'BRL', 'billing_interval' => 'monthly', 'is_featured' => false];

    protected $fillable = [
        'name', 'slug', 'description', 'price_cents', 'currency', 'billing_interval', 'is_featured',
        'is_published', 'usage_mode', 'uses_per_cycle', 'max_future_bookings', 'max_per_day', 'max_per_week',
        'min_days_between_uses', 'min_advance_minutes', 'max_advance_days', 'allowed_weekdays',
        'allowed_time_start', 'allowed_time_end', 'all_barbers', 'min_commitment_months', 'grace_days',
        'guest_policy', 'no_show_policy', 'cancellation_policy', 'benefits', 'terms', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'usage_mode' => PlanUsageMode::class,
            'allowed_weekdays' => 'array',
            'all_barbers' => 'boolean',
            'benefits' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $plan) {
            if ($plan->isDirty(self::COMMERCIAL_FIELDS)) {
                $plan->version = ($plan->getOriginal('version') ?? 1) + 1;
            }
        });
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'plan_service_rules')
            ->withPivot('id', 'uses_per_cycle');
    }

    public function barbers(): BelongsToMany
    {
        return $this->belongsToMany(Barber::class, 'barber_membership_plan');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function formattedPrice(): string
    {
        return Money::format($this->price_cents);
    }

    public function usageSummary(): string
    {
        return match ($this->usage_mode) {
            PlanUsageMode::Unlimited => 'Uso ilimitado conforme regras do plano',
            PlanUsageMode::LimitedPerService => 'Franquia definida por serviço',
            default => $this->uses_per_cycle
                ? $this->uses_per_cycle.' '.($this->uses_per_cycle === 1 ? 'uso' : 'usos').' por mês'
                : 'Franquia a definir',
        };
    }

    /** Lista legível das regras de uso, sem inventar nada além do cadastrado. */
    public function ruleLines(): array
    {
        $lines = [];
        if ($this->max_future_bookings) {
            $lines[] = "Até {$this->max_future_bookings} agendamento(s) futuro(s) por vez";
        }
        if ($this->max_per_day) {
            $lines[] = "Máximo de {$this->max_per_day} uso(s) por dia";
        }
        if ($this->max_per_week) {
            $lines[] = "Máximo de {$this->max_per_week} uso(s) por semana";
        }
        if ($this->min_days_between_uses) {
            $lines[] = "Intervalo mínimo de {$this->min_days_between_uses} dia(s) entre usos";
        }
        if ($this->allowed_weekdays) {
            $names = collect($this->allowed_weekdays)->sort()
                ->map(fn ($d) => ['', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'][(int) $d] ?? '')->implode(', ');
            $lines[] = "Válido em: {$names}";
        }
        if ($this->allowed_time_start && $this->allowed_time_end) {
            $lines[] = 'Horários das '.substr($this->allowed_time_start, 0, 5).' às '.substr($this->allowed_time_end, 0, 5);
        }
        if ($this->min_commitment_months) {
            $lines[] = "Permanência mínima de {$this->min_commitment_months} mês(es)";
        }

        return $lines;
    }
}
