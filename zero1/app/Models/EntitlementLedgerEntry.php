<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntitlementLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'subscription_entitlement_ledger';

    protected $fillable = ['subscription_id', 'billing_cycle_id', 'appointment_id', 'service_id', 'kind', 'reserved_delta', 'consumed_delta', 'note', 'created_by'];
}
