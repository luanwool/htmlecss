<?php

namespace App\Models;

use App\Enums\BarberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barber extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => 'active', 'is_public' => true, 'accepts_any_assignment' => true, 'sort_order' => 0];

    protected $fillable = [
        'user_id', 'display_name', 'slug', 'bio', 'specialties', 'photo_media_id',
        'status', 'is_public', 'accepts_any_assignment', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'status' => BarberStatus::class,
            'is_public' => 'boolean',
            'accepts_any_assignment' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BarberWorkSchedule::class)->orderBy('weekday')->orderBy('starts_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', BarberStatus::Active->value);
    }

    public function scopeVisibleOnSite(Builder $q): Builder
    {
        return $q->where('is_public', true)->where('status', '!=', BarberStatus::Inactive->value);
    }

    public function initials(): string
    {
        $clean = trim(preg_replace('/[^\p{L}\s]+/u', ' ', $this->display_name));

        return collect(preg_split('/\s+/', $clean))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
