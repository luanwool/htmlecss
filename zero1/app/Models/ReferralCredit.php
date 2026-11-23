<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCredit extends Model
{
    protected $fillable = ['customer_id', 'referral_id', 'role', 'amount_cents', 'used_cents', 'expires_on', 'note', 'granted_by'];

    protected function casts(): array
    {
        return ['expires_on' => 'date:Y-m-d'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function uses(): HasMany
    {
        return $this->hasMany(ReferralCreditUse::class);
    }

    /** Saldo em centavos (diferença entre o crédito total e o já consumido). */
    public function balance(): int
    {
        return max(0, (int) $this->amount_cents - (int) $this->used_cents);
    }

    public function isActive(): bool
    {
        return $this->balance() > 0 && (! $this->expires_on || $this->expires_on->isFuture());
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereColumn('used_cents', '<', 'amount_cents')
            ->where(fn ($w) => $w->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString()));
    }
}
