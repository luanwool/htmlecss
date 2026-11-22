<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    public const KEYS = [
        'hero' => 'Hero (abertura)',
        'services' => 'Serviços e preços',
        'plans' => 'Clube Zero 1 (planos)',
        'team' => 'Profissionais',
        'gallery' => 'Galeria',
        'testimonials' => 'Depoimentos',
        'faq' => 'Perguntas frequentes',
        'location' => 'Localização',
        'cta' => 'Chamada final',
    ];

    protected $fillable = ['key', 'eyebrow', 'title', 'subtitle', 'content', 'media_id', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return ['content' => 'array', 'is_visible' => 'boolean'];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    public function field(string $key, mixed $default = null): mixed
    {
        $value = $this->content[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public function label(): string
    {
        return self::KEYS[$this->key] ?? $this->key;
    }
}
