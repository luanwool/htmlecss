<?php

namespace App\Models;

use App\Enums\CycleStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    protected $fillable = [
        'subscription_id', 'sequence', 'period_start', 'period_end', 'due_on', 'amount_cents', 'allowance', 'status',
        'gateway_payment_id', 'fee_cents', 'net_cents', 'paid_at', 'settled_at', 'receipt_url',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d',
            'status' => CycleStatus::class, 'paid_at' => 'datetime', 'settled_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(EntitlementLedgerEntry::class);
    }

    public function externalReference(): string
    {
        return "z1-sub{$this->subscription_id}-c{$this->sequence}";
    }

    public static function findByReference(?string $ref): ?self
    {
        if (! $ref || ! preg_match('/^z1-sub(\d+)-c(\d+)$/', $ref, $m)) {
            return null;
        }

        return static::query()->where('subscription_id', $m[1])->where('sequence', $m[2])->first();
    }

    /** Último dia (inclusivo) do período, para exibição. */
    public function lastDay(): Carbon
    {
        return $this->period_end->copy()->subDay();
    }
}
