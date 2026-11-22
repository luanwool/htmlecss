<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    protected $fillable = [
        'collection', 'disk', 'path', 'variants', 'original_name', 'mime_type', 'size_bytes',
        'width', 'height', 'alt_text', 'caption', 'category', 'is_published', 'sort_order', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['variants' => 'array', 'is_published' => 'boolean'];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function url(?int $width = null): string
    {
        $variants = $this->variants ?? [];
        if ($width !== null && $variants) {
            ksort($variants);
            foreach ($variants as $w => $path) {
                if ((int) $w >= $width) {
                    return Storage::disk($this->disk)->url($path);
                }
            }

            return Storage::disk($this->disk)->url(end($variants));
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function srcset(): string
    {
        $variants = $this->variants ?? [];
        ksort($variants);

        return collect($variants)
            ->map(fn ($path, $w) => Storage::disk($this->disk)->url($path).' '.$w.'w')
            ->implode(', ');
    }

    public function allPaths(): array
    {
        return array_values(array_unique(array_merge([$this->path], array_values($this->variants ?? []))));
    }
}
