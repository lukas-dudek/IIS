<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';
$root = realpath(__DIR__);
$file = realpath(__DIR__ . $path);

if (
    $path !== '/'
    && $root !== false
    && $file !== false
    && str_starts_with($file, $root . DIRECTORY_SEPARATOR)
    && is_file($file)
) {
    return false;
}

require __DIR__ . '/index.php';
