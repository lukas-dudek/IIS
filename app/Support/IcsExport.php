<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class IcsExport
{
    /**
     * @param iterable<Event> $events
     */
    public static function build(iterable $events, string $host, string $baseUrl, ?CarbonImmutable $stamp = null): string
    {
        $host = trim($host) !== '' ? $host : 'iis.local';
        $baseUrl = rtrim($baseUrl, '/');
        $dtstamp = ($stamp ?? utc_now())->setTimezone('UTC')->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//IIS Udalosti//CS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Moje udalosti',
            'X-WR-TIMEZONE:Europe/Prague',
        ];

        foreach ($events as $event) {
            $venue = $event->venue;
            $location = $venue === null ? '' : $venue->fullAddress();
            $description = trim((string) $event->description);
            if ($event->category !== null) {
                $description .= ($description === '' ? '' : "\n") . 'Kategorie: ' . $event->category->name;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:event-' . $event->id . '@' . $host;
            $lines[] = 'DTSTAMP:' . $dtstamp;
            $lines[] = 'DTSTART:' . self::formatDateTime($event->starts_at);
            $lines[] = 'DTEND:' . self::formatDateTime($event->ends_at);
            $lines[] = 'SUMMARY:' . self::escape($event->title);
            if ($location !== '') {
                $lines[] = 'LOCATION:' . self::escape($location);
            }
            if ($description !== '') {
                $lines[] = 'DESCRIPTION:' . self::escape($description);
            }
            if ($baseUrl !== '') {
                $lines[] = 'URL:' . $baseUrl . '/udalosti/' . $event->slug;
            }
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    public static function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $text
        );
    }

    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $first = true;
        while (strlen($line) > 75) {
            $chunk = mb_strcut($line, 0, 75, 'UTF-8');
            if ($chunk === '') {
                $chunk = substr($line, 0, 75);
            }
            $line = substr($line, strlen($chunk));
            $result .= ($first ? '' : "\r\n ") . $chunk;
            $first = false;
        }

        return $result . ($first ? '' : "\r\n ") . $line;
    }

    private static function formatDateTime(?CarbonInterface $moment): string
    {
        return $moment === null ? '' : $moment->setTimezone('UTC')->format('Ymd\THis\Z');
    }
}
