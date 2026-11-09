<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\HttpException;

function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function old(string $key, mixed $default = ''): mixed
{
    $values = $_SESSION['_old'] ?? [];

    return is_array($values) && array_key_exists($key, $values) ? $values[$key] : $default;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function selected(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrátor',
        'moderator' => 'Moderátor',
        'user' => 'Uživatel',
        default => $role,
    };
}

function venue_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Čeká na schválení',
        'approved' => 'Schváleno',
        'rejected' => 'Zamítnuto',
        default => $status,
    };
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}
