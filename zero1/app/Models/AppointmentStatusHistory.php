<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'appointment_status_history';

    protected $fillable = ['appointment_id', 'from_status', 'to_status', 'changes', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
