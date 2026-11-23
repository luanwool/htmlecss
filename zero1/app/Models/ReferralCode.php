<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCode extends Model
{
    protected $fillable = ['customer_id', 'code', 'is_active', 'visits_count', 'signups_count'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** URL pública (curta) que gera o cookie e redireciona para a página inicial. */
    public function url(): string
    {
        return url('/i/'.$this->code);
    }
}
