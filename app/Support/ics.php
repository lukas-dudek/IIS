<?php

declare(strict_types=1);

function ics_escape(string $text): string
{
    return str_replace(
        ['\\', ';', ',', "\r\n", "\n", "\r"],
        ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
        $text
    );
}

function ics_datetime(string $utc): string
{
    $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

    return $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

function ics_fold(string $line): string
{
    $result = '';
    $first = true;

    while (strlen($line) > ($first ? 75 : 74)) {
        $limit = $first ? 75 : 74;
        $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
        if ($chunk === '') {
            $chunk = substr($line, 0, $limit);
        }
        $line = substr($line, strlen($chunk));
        $result .= ($first ? '' : "\r\n ") . $chunk;
        $first = false;
    }

    return $result . ($first ? '' : "\r\n ") . $line;
}

function build_ics(array $events, string $host, string $baseUrl, ?string $dtstampUtc = null): string
{
    $host = $host !== '' ? $host : 'iis.local';
    $baseUrl = rtrim($baseUrl, '/');
    $dtstamp = $dtstampUtc ?? now_utc();

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//IIS Události//CS',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:Moje události',
        'X-WR-TIMEZONE:Europe/Prague',
    ];

    foreach ($events as $event) {
        $location = trim(implode(' ', array_filter([
            $event['venue_name'] ?? '',
            $event['venue_street'] ?? '',
            trim(($event['venue_postal_code'] ?? '') . ' ' . ($event['venue_city'] ?? '')),
        ])));

        $description = trim((string) ($event['description'] ?? ''));
        if (!empty($event['category_name'])) {
            $description .= ($description === '' ? '' : "\n") . 'Kategorie: ' . $event['category_name'];
        }

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:event-' . (int) $event['id'] . '@' . $host;
        $lines[] = 'DTSTAMP:' . ics_datetime($dtstamp);
        $lines[] = 'DTSTART:' . ics_datetime((string) $event['starts_at']);
        $lines[] = 'DTEND:' . ics_datetime((string) $event['ends_at']);
        $lines[] = 'SUMMARY:' . ics_escape((string) $event['title']);
        if ($location !== '') {
            $lines[] = 'LOCATION:' . ics_escape($location);
        }
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . ics_escape($description);
        }
        if ($baseUrl !== '') {
            $lines[] = 'URL:' . $baseUrl . '/udalosti/' . $event['slug'];
        }
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}
