<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;

/**
 * Semua gambar di-decode lalu di-encode ulang ke WebP: ukuran lebih kecil,
 * metadata EXIF (lokasi GPS dsb) terhapus, dan payload tersembunyi ikut hilang.
 */
class ImageService
{
    public function store(UploadedFile $file, string $directory, string $disk = 'public', int $maxWidth = 1600, bool $watermark = false): string
    {
        return $this->storeFromPath($file->getRealPath(), $directory, $disk, $maxWidth, $watermark);
    }

    /** Sama seperti store(), dari file yang sudah ada di server (mis. memindahkan foto privat ke publik). */
    public function storeFromPath(string $absolutePath, string $directory, string $disk = 'public', int $maxWidth = 1600, bool $watermark = false): string
    {
        $image = Image::decodePath($absolutePath)->orient()->scaleDown($maxWidth);

        if ($watermark && config('auction.watermark.enabled')) {
            $this->watermark($image);
        }

        $encoded = $image->encode(new WebpEncoder(quality: 80, strip: true));

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        Storage::disk($disk)->put($path, $encoded->toString());

        return $path;
    }

    /** Teks semi-transparan di pojok kanan bawah, ukurannya menyesuaikan lebar foto. */
    public function watermark(ImageInterface $image): ImageInterface
    {
        $text = config('auction.watermark.text') ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.name'));
        $size = max(12, (int) round(min($image->width(), $image->height() * 1.5) / 28));
        $margin = (int) round($size * 0.8);
        $alpha = (float) config('auction.watermark.opacity', 0.55);

        $x = $image->width() - $margin;
        $y = $image->height() - $margin;
        $shadow = max(1, (int) round($size / 12));
        $style = fn (string $color) => function (FontFactory $font) use ($size, $color) {
            $font->filename(resource_path('fonts/DejaVuSans-Bold.ttf'));
            $font->size($size);
            $font->color($color);
            $font->align('right', 'bottom');
        };

        // Bayangan gelap dulu supaya teks tetap terbaca di foto yang terang maupun gelap.
        return $image
            ->text($text, $x + $shadow, $y + $shadow, $style('rgba(0, 0, 0, '.round($alpha * 0.7, 2).')'))
            ->text($text, $x, $y, $style("rgba(255, 255, 255, {$alpha})"));
    }
}
