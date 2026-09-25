<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Semua gambar di-decode lalu di-encode ulang ke WebP: ukuran lebih kecil,
 * metadata EXIF (lokasi GPS dsb) terhapus, dan payload tersembunyi ikut hilang.
 */
class ImageService
{
    public function store(UploadedFile $file, string $directory, string $disk = 'public', int $maxWidth = 1600): string
    {
        return $this->storeFromPath($file->getRealPath(), $directory, $disk, $maxWidth);
    }

    /** Sama seperti store(), dari file yang sudah ada di server (mis. memindahkan foto privat ke publik). */
    public function storeFromPath(string $absolutePath, string $directory, string $disk = 'public', int $maxWidth = 1600): string
    {
        $encoded = Image::decodePath($absolutePath)
            ->orient()
            ->scaleDown($maxWidth)
            ->encode(new WebpEncoder(quality: 80, strip: true));

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        Storage::disk($disk)->put($path, $encoded->toString());

        return $path;
    }
}
