<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['buffer_minutes' => 0, 'is_public' => true, 'is_bookable_standalone' => true, 'is_plan_eligible' => false, 'is_active' => true, 'price_is_from' => false];

    protected $fillable = [
        'service_category_id', 'name', 'slug', 'description', 'price_cents', 'price_is_from',
        'duration_minutes', 'buffer_minutes', 'min_advance_minutes', 'is_public',
        'is_bookable_standalone', 'is_plan_eligible', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'price_is_from' => 'boolean',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'is_public' => 'boolean',
            'is_bookable_standalone' => 'boolean',
            'is_plan_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function barbers(): BelongsToMany
    {
        return $this->belongsToMany(Barber::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopePubliclyListed(Builder $q): Builder
    {
        return $q->active()->where('is_public', true);
    }

    /** Duração efetiva que ocupa a agenda = serviço + intervalo operacional. */
    public function effectiveDurationMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    public function formattedPrice(): string
    {
        $price = Money::format($this->price_cents);

        return $this->price_is_from && $this->price_cents !== null ? 'a partir de '.$price : $price;
    }

    public function formattedDuration(): string
    {
        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        return match (true) {
            $h && $m => "{$h}h{$m}",
            $h > 0 => "{$h}h",
            default => "{$m} min",
        };
    }
}
