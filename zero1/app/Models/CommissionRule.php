<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    protected $fillable = ['barber_id', 'service_id', 'percent', 'plan_visit_cents'];

    protected function casts(): array
    {
        return ['percent' => 'float'];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }
}
