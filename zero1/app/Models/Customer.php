<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'internal_notes', 'origin',
        'terms_accepted_at', 'privacy_accepted_at', 'marketing_consent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ServiceVisit::class);
    }

    /** Assinatura viva mais recente (no máximo uma por cliente). */
    public function liveSubscription(): ?Subscription
    {
        return $this->subscriptions()->live()->latest('id')->first();
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }
}
