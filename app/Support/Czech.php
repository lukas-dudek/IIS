<?php

declare(strict_types=1);

namespace App\Support;

final class Czech
{
    public static function plural(int $count, string $one, string $few, string $many): string
    {
        if ($count === 1) {
            return $one;
        }
        if ($count >= 2 && $count <= 4) {
            return $few;
        }

        return $many;
    }
}
