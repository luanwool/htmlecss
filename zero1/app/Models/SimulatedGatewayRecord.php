<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulatedGatewayRecord extends Model
{
    protected $fillable = ['kind', 'external_id', 'parent_id', 'external_reference', 'status', 'method', 'amount_cents', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }
}
