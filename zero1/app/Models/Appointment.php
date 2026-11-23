<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $fillable = [
        'uid', 'customer_id', 'barber_id', 'service_id', 'starts_at', 'ends_at', 'occupied_until',
        'duration_minutes_snapshot', 'buffer_minutes_snapshot', 'service_name_snapshot', 'price_cents_snapshot',
        'status', 'mode', 'origin', 'subscription_id', 'billing_cycle_id', 'plan_payment_issue', 'notes',
        'ics_sequence', 'cancelled_at', 'cancel_reason', 'reminder_24h_sent_at', 'reminder_2h_sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'occupied_until' => 'datetime',
            'cancelled_at' => 'datetime', 'reminder_24h_sent_at' => 'datetime', 'reminder_2h_sent_at' => 'datetime',
            'status' => AppointmentStatus::class, 'plan_payment_issue' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function visit(): HasOne
    {
        return $this->hasOne(ServiceVisit::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->latest('id');
    }

    public function scopeOccupying(Builder $q): Builder
    {
        return $q->whereIn('status', AppointmentStatus::occupying());
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::InProgress->value])
            ->where('starts_at', '>=', now()->subHours(1));
    }

    public function isPlan(): bool
    {
        return $this->mode === 'plan';
    }

    public function localStart(): CarbonImmutable
    {
        return BusinessTime::local($this->starts_at);
    }

    public function localEnd(): CarbonImmutable
    {
        return BusinessTime::local($this->ends_at);
    }
}
