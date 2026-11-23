<?php

namespace App\Models;

use App\Enums\CycleStatus;
use App\Enums\SubscriptionStatus;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'customer_id', 'membership_plan_id', 'plan_version', 'plan_snapshot', 'price_cents', 'status', 'payment_method',
        'gateway', 'gateway_subscription_id', 'checkout_url', 'terms_accepted_at', 'activated_at', 'cancel_requested_at',
        'cancel_effective_on', 'cancelled_at', 'cancel_reason', 'fraud_blocked', 'last_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_snapshot' => 'array', 'status' => SubscriptionStatus::class, 'terms_accepted_at' => 'datetime',
            'activated_at' => 'datetime', 'cancel_requested_at' => 'datetime', 'cancel_effective_on' => 'date:Y-m-d',
            'cancelled_at' => 'datetime', 'fraud_blocked' => 'boolean', 'last_reconciled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id')->withTrashed();
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(BillingCycle::class)->orderBy('sequence');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereIn('status', SubscriptionStatus::live());
    }

    /** Termo do snapshot congelado na adesão (nunca do plano atual). */
    public function term(string $key, mixed $default = null): mixed
    {
        return $this->plan_snapshot[$key] ?? $default;
    }

    public function latestCycle(): ?BillingCycle
    {
        return $this->cycles()->reorder('sequence', 'desc')->first();
    }

    /** Ciclo PAGO que cobre a data local informada — regra central de liberação do benefício. */
    public function paidCycleCovering(CarbonInterface $moment): ?BillingCycle
    {
        $date = BusinessTime::local($moment)->toDateString();

        return $this->cycles()
            ->where('status', CycleStatus::Paid->value)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>', $date)
            ->first();
    }

    public function currentPaidCycle(): ?BillingCycle
    {
        return $this->paidCycleCovering(now());
    }
}
