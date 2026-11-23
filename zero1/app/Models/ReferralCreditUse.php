<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCreditUse extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['referral_credit_id', 'service_visit_id', 'amount_cents'];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(ReferralCredit::class, 'referral_credit_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class, 'service_visit_id');
    }
}
