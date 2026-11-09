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

function moderation_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Čeká na schválení',
        'approved' => 'Schváleno',
        'rejected' => 'Zamítnuto',
        default => $status,
    };
}

function event_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Koncept',
        'pending' => 'Čeká na schválení',
        'approved' => 'Schváleno',
        'rejected' => 'Zamítnuto',
        'cancelled' => 'Zrušeno',
        default => $status,
    };
}

function registration_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Čeká na platbu',
        'confirmed' => 'Potvrzeno',
        'cancelled' => 'Zrušeno',
        default => $status,
    };
}

function event_price_range(?string $minPrice, ?string $maxPrice): string
{
    if ($minPrice === null || $maxPrice === null) {
        return '—';
    }

    $min = (float) $minPrice;
    $max = (float) $maxPrice;
    if ($min === 0.0 && $max === 0.0) {
        return 'Zdarma';
    }
    if ($min === $max) {
        return format_money($min);
    }

    return number_format($min, 0, ',', ' ') . '–' . format_money($max);
}

function event_occupancy(int $active, ?int $capacity): string
{
    return $active . ' / ' . ($capacity === null ? 'neomezeno' : $capacity);
}

function plural_results(int $count): string
{
    $mod100 = $count % 100;
    $mod10 = $count % 10;

    if ($mod100 >= 11 && $mod100 <= 14) {
        return 'výsledků';
    }
    if ($mod10 === 1) {
        return 'výsledek';
    }
    if ($mod10 >= 2 && $mod10 <= 4) {
        return 'výsledky';
    }

    return 'výsledků';
}

function czech_month_name(int $month): string
{
    return [
        1 => 'leden',
        2 => 'únor',
        3 => 'březen',
        4 => 'duben',
        5 => 'květen',
        6 => 'červen',
        7 => 'červenec',
        8 => 'srpen',
        9 => 'září',
        10 => 'říjen',
        11 => 'listopad',
        12 => 'prosinec',
    ][$month] ?? '';
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}
