<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionLedgerEntry extends Model
{
    protected $table = 'commission_ledger';

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class, 'service_visit_id');
    }

    protected $fillable = ['service_visit_id', 'barber_id', 'amount_cents', 'basis', 'paid_out_at'];
}
