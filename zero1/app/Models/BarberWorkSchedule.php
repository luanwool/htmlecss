<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarberWorkSchedule extends Model
{
    protected $fillable = ['barber_id', 'weekday', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
