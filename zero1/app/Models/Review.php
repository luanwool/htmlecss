<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'author_name', 'content', 'rating', 'source', 'reviewed_on', 'authorization_confirmed_at',
        'authorization_note', 'authorization_confirmed_by', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_on' => 'date',
            'authorization_confirmed_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /** Só aparece no site se publicado E com autorização registrada. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->whereNotNull('authorization_confirmed_at');
    }
}
