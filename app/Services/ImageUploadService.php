<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validated image upload handler for stories and campaigns.
 *
 * Validation rules (enforced at controller level via Laravel Validator):
 *   - Max size   : 2 048 KB
 *   - Mime types : image/jpeg, image/png, image/webp
 *
 * Resizing is done with PHP GD (bundled with Laravel's default PHP).
 */
final class ImageUploadService
{
    public const MIMES  = 'jpg,jpeg,png,webp';
    public const MAX_KB = 2048;

    /**
     * Store an uploaded image and return its relative storage path.
     *
     * @param  int|null  $maxWidth   Downsample if wider than this (aspect-ratio preserved)
     * @param  int|null  $maxHeight  Downsample if taller than this (aspect-ratio preserved)
     */
    public function store(
        UploadedFile $file,
        string $disk   = 'public',
        string $folder = 'uploads',
        ?int $maxWidth  = null,
        ?int $maxHeight = null,
    ): string {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = Str::uuid()->toString() . '.' . $extension;
        $path      = $folder . '/' . $filename;

        if (($maxWidth || $maxHeight) && extension_loaded('gd')) {
            $contents = $this->resizeWithGd($file, $extension, $maxWidth, $maxHeight);
            Storage::disk($disk)->put($path, $contents);
        } else {
            Storage::disk($disk)->putFileAs($folder, $file, $filename);
        }

        return $path;
    }

    public function delete(?string $path, string $disk = 'public'): void
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function resizeWithGd(UploadedFile $file, string $ext, ?int $maxW, ?int $maxH): string
    {
        $src = match ($ext) {
            'png'  => imagecreatefrompng($file->getRealPath()),
            'webp' => imagecreatefromwebp($file->getRealPath()),
            default => imagecreatefromjpeg($file->getRealPath()),
        };

        if ($src === false) {
            return file_get_contents($file->getRealPath());
        }

        [$origW, $origH] = [imagesx($src), imagesy($src)];
        [$newW, $newH]   = $this->scaleDimensions($origW, $origH, $maxW, $maxH);

        if ($newW === $origW && $newH === $origH) {
            imagedestroy($src);
            return file_get_contents($file->getRealPath());
        }

        $dst = imagecreatetruecolor($newW, $newH);

        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        ob_start();
        match ($ext) {
            'png'  => imagepng($dst, null, 8),
            'webp' => imagewebp($dst, null, 85),
            default => imagejpeg($dst, null, 85),
        };
        $contents = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $contents;
    }

    /** @return array{int, int} */
    private function scaleDimensions(int $origW, int $origH, ?int $maxW, ?int $maxH): array
    {
        if (! $maxW && ! $maxH) {
            return [$origW, $origH];
        }

        $ratioW = $maxW ? $maxW / $origW : PHP_FLOAT_MAX;
        $ratioH = $maxH ? $maxH / $origH : PHP_FLOAT_MAX;
        $ratio  = min($ratioW, $ratioH, 1.0);

        return [(int) round($origW * $ratio), (int) round($origH * $ratio)];
    }
}
