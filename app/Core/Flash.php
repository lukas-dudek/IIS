<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const KEY = '_flash';
    private const TYPES = ['success', 'danger', 'warning', 'info'];

    public static function set(string $type, string $message): void
    {
        if (!in_array($type, self::TYPES, true)) {
            $type = 'info';
        }

        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    public static function has(): bool
    {
        return isset($_SESSION[self::KEY]) && $_SESSION[self::KEY] !== [];
    }

    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return is_array($messages) ? $messages : [];
    }
}
