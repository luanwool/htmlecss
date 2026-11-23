<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = ['referrer_customer_id', 'referred_customer_id', 'code', 'status', 'first_visit_at', 'rewarded_at', 'snapshot'];

    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
            'snapshot' => 'array',
            'first_visit_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id')->withTrashed();
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id')->withTrashed();
    }
}
