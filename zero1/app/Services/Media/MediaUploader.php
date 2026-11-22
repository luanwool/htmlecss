<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Armazena imagens e gera variantes WebP responsivas com GD (disponível na hospedagem compartilhada).
 * Metadados EXIF (inclusive geolocalização) são descartados ao reprocessar.
 */
class MediaUploader
{
    public function store(UploadedFile $file, string $collection, array $attributes = []): MediaAsset
    {
        $disk = config('zero1.media.disk');
        $dir = 'media/'.$collection.'/'.now()->format('Y/m');
        $base = Str::lower(Str::random(20));

        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        $source = $this->load($file->getRealPath(), $file->getMimeType());

        $variants = [];
        $originalPath = "{$dir}/{$base}.webp";

        if ($source && function_exists('imagewebp')) {
            $source = $this->orient($source, $file->getRealPath(), $file->getMimeType());
            $width = imagesx($source);
            $height = imagesy($source);

            Storage::disk($disk)->put($originalPath, $this->encode($source, min($width, 2400)));
            foreach (config('zero1.media.widths') as $w) {
                if ($w < $width) {
                    $path = "{$dir}/{$base}-{$w}.webp";
                    Storage::disk($disk)->put($path, $this->encode($source, $w));
                    $variants[$w] = $path;
                }
            }
            $variants[min($width, 2400)] = $originalPath;
            if ($width > 2400) {
                $height = (int) round($height * 2400 / $width);
                $width = 2400;
            }
            imagedestroy($source);
            $mime = 'image/webp';
            $size = Storage::disk($disk)->size($originalPath);
        } else {
            // Sem GD/WebP: guarda o arquivo como enviado.
            $originalPath = $file->storeAs($dir, $base.'.'.$file->extension(), $disk);
            $mime = $file->getMimeType();
            $size = $file->getSize();
        }

        return MediaAsset::create(array_merge([
            'collection' => $collection,
            'disk' => $disk,
            'path' => $originalPath,
            'variants' => $variants ?: null,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mime,
            'size_bytes' => $size,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => auth()->id(),
        ], $attributes));
    }

    public function delete(MediaAsset $media): void
    {
        Storage::disk($media->disk)->delete($media->allPaths());
        $media->delete();
    }

    private function load(string $path, ?string $mime): \GdImage|false|null
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => throw new RuntimeException('Formato de imagem não suportado.'),
        };
    }

    private function orient(\GdImage $img, string $path, ?string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $img;
        }
        $orientation = @exif_read_data($path)['Orientation'] ?? 1;

        return match ((int) $orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    private function encode(\GdImage $source, int $targetWidth): string
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $img = $source;
        if ($targetWidth < $w) {
            $img = imagescale($source, $targetWidth, (int) round($h * $targetWidth / $w), IMG_BICUBIC);
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        ob_start();
        imagewebp($img, null, config('zero1.media.quality'));
        $data = ob_get_clean();
        if ($img !== $source) {
            imagedestroy($img);
        }

        return $data;
    }
}
