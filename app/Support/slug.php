<?php

declare(strict_types=1);

function slugify(string $text, int $maxLength = 180): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
        'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o',
        'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u',
        'ý' => 'y', 'ž' => 'z', 'ß' => 'ss',
    ]);

    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    if ($text === '') {
        $text = 'polozka';
    }

    if (strlen($text) > $maxLength) {
        $text = rtrim(substr($text, 0, $maxLength), '-');
    }

    return $text;
}
