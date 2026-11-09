<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Upload
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private string $directory, private int $maxBytes)
    {
    }

    public function store(array $file, string $folder): string
    {
        if (preg_match('/^[a-z0-9_-]+$/', $folder) !== 1) {
            throw new RuntimeException('Neplatná složka pro upload.');
        }

        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error)) {
            throw new RuntimeException('Soubor se nepodařilo nahrát.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError($error));
        }

        $temporary = $file['tmp_name'] ?? null;
        $size = $file['size'] ?? null;
        if (!is_string($temporary) || !is_int($size) || $size <= 0) {
            throw new RuntimeException('Soubor se nepodařilo načíst.');
        }
        if ($size > $this->maxBytes) {
            $megabytes = max(1, (int) round($this->maxBytes / 1024 / 1024));
            throw new RuntimeException(sprintf('Soubor je příliš velký. Maximum je %d MB.', $megabytes));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($temporary);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Povolené jsou pouze obrázky JPEG, PNG a WebP.');
        }

        $image = @getimagesize($temporary);
        if ($image === false || (int) $image[0] <= 0 || (int) $image[1] <= 0) {
            throw new RuntimeException('Soubor není platný obrázek.');
        }

        $targetDirectory = rtrim($this->directory, '/') . '/' . $folder;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Nepodařilo se vytvořit složku pro upload.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $target = $targetDirectory . '/' . $name;
        if (!move_uploaded_file($temporary, $target)) {
            throw new RuntimeException('Soubor se nepodařilo uložit.');
        }

        return $folder . '/' . $name;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        if (preg_match('#^[a-z0-9_-]+/[a-f0-9]{32}\.(jpg|png|webp)$#', $relativePath) !== 1) {
            return;
        }

        $file = rtrim($this->directory, '/') . '/' . $relativePath;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
            UPLOAD_ERR_PARTIAL => 'Soubor se nepodařilo přenést celý.',
            UPLOAD_ERR_NO_FILE => 'Vyberte soubor.',
            UPLOAD_ERR_NO_TMP_DIR => 'Na serveru chybí dočasná složka.',
            UPLOAD_ERR_CANT_WRITE => 'Soubor se nepodařilo uložit.',
            UPLOAD_ERR_EXTENSION => 'Upload byl zastaven serverem.',
            default => 'Soubor se nepodařilo nahrát.',
        };
    }
}
