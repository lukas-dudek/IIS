<?php

declare(strict_types=1);

use App\Support\Czech;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

if (!function_exists('prague_now')) {
    function prague_now(): CarbonImmutable
    {
        return CarbonImmutable::now('Europe/Prague');
    }
}

if (!function_exists('utc_now')) {
    function utc_now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}

if (!function_exists('utc_to_prague')) {
    function utc_to_prague(?string $utc): ?CarbonImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }

        return CarbonImmutable::parse($utc, 'UTC')->setTimezone('Europe/Prague');
    }
}

if (!function_exists('prague_to_utc')) {
    function prague_to_utc(?string $local): ?CarbonImmutable
    {
        if ($local === null || $local === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $local, 'Europe/Prague')->setTimezone('UTC');
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?CarbonInterface $moment, string $format = 'j. n. Y H:i'): string
    {
        if ($moment === null) {
            return '';
        }

        return $moment->setTimezone('Europe/Prague')->format($format);
    }
}

if (!function_exists('format_date_range')) {
    function format_date_range(?CarbonInterface $startsAt, ?CarbonInterface $endsAt): string
    {
        if ($startsAt === null || $endsAt === null) {
            return '';
        }

        $start = $startsAt->setTimezone('Europe/Prague');
        $end = $endsAt->setTimezone('Europe/Prague');

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return $start->format('j. n. Y H:i') . '–' . $end->format('H:i');
        }

        return $start->format('j. n. Y H:i') . ' – ' . $end->format('j. n. Y H:i');
    }
}

if (!function_exists('format_money')) {
    function format_money(string|int|float|null $amount): string
    {
        $number = (float) $amount;
        $decimals = abs($number - round($number)) < 0.005 ? 0 : 2;

        return number_format($number, $decimals, ',', ' ') . ' Kč';
    }
}

if (!function_exists('czech_month_name')) {
    function czech_month_name(int $month): string
    {
        return [
            1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben',
            5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen',
            9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
        ][$month] ?? '';
    }
}

if (!function_exists('czech_plural')) {
    function czech_plural(int $count, string $one, string $few, string $many): string
    {
        return Czech::plural($count, $one, $few, $many);
    }
}

if (!function_exists('default_event_times')) {
    /**
     * @return array{starts_at: string, ends_at: string}
     */
    function default_event_times(): array
    {
        $start = prague_now()->setTime((int) prague_now()->format('G'), 0)->addHour();

        return [
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $start->addHours(2)->format('Y-m-d\TH:i'),
        ];
    }
}
