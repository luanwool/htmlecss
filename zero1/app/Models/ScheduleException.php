<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleException extends Model
{
    protected $fillable = ['barber_id', 'date', 'is_closed', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_closed' => 'boolean'];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }
}
