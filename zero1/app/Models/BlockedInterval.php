<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedInterval extends Model
{
    protected $fillable = ['barber_id', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }
}
