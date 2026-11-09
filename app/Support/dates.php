<?php

declare(strict_types=1);

function utc_to_prague(string $utc): ?DateTimeImmutable
{
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Prague'));
    } catch (Exception) {
        return null;
    }
}

function prague_to_utc(string $value, string $format = 'Y-m-d\TH:i'): ?string
{
    $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('Europe/Prague'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function format_datetime(?string $utc, string $format = 'j. n. Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '';
    }

    $date = utc_to_prague($utc);

    return $date === null ? '' : $date->format($format);
}

function format_date_range(?string $startsAt, ?string $endsAt): string
{
    $start = $startsAt === null ? null : utc_to_prague($startsAt);
    $end = $endsAt === null ? null : utc_to_prague($endsAt);
    if ($start === null || $end === null) {
        return '';
    }

    if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
        return $start->format('j. n. Y H:i') . '–' . $end->format('H:i');
    }

    return $start->format('j. n. Y H:i') . ' – ' . $end->format('j. n. Y H:i');
}

function format_money(float|string $amount): string
{
    $number = (float) $amount;
    $decimals = abs($number - round($number)) < 0.005 ? 0 : 2;

    return number_format($number, $decimals, ',', ' ') . ' Kč';
}

function now_utc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function is_past(?string $utc): bool
{
    if ($utc === null || $utc === '') {
        return false;
    }

    try {
        return new DateTimeImmutable($utc, new DateTimeZone('UTC')) < new DateTimeImmutable('now', new DateTimeZone('UTC'));
    } catch (Exception) {
        return false;
    }
}
