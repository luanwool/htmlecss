<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = ['subscription_id', 'billing_cycle_id', 'gateway', 'gateway_payment_id', 'status', 'method', 'amount_cents', 'fee_cents', 'net_cents', 'paid_at', 'details'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'details' => 'array'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class, 'billing_cycle_id');
    }
}
