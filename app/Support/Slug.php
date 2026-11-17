<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class Slug
{
    /**
     * Czech-aware slug without diacritics, safe for URLs.
     */
    public static function make(string $value, int $maxLength): string
    {
        $slug = Str::slug($value);
        $slug = trim(mb_substr($slug, 0, $maxLength), '-');

        return $slug === '' ? 'polozka' : $slug;
    }
}
