<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ukládá obrázky do veřejného disku pod náhodným názvem.
 */
final class ImageUpload
{
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(UploadedFile $file, string $directory): string
    {
        if (!$file->isValid()) {
            throw new RuntimeException('Soubor se nepodařilo nahrát.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Obrázek může mít nejvýše 2 MB.');
        }

        $mime = (string) $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Povolené formáty obrázku jsou JPEG, PNG a WebP.');
        }
        if (@getimagesize($file->getRealPath()) === false) {
            throw new RuntimeException('Soubor není platný obrázek.');
        }

        $extension = self::EXTENSIONS[$mime];
        $name = Str::random(32) . '.' . $extension;

        Storage::disk('public')->put($directory . '/' . $name, (string) file_get_contents($file->getRealPath()));

        return $directory . '/' . $name;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
