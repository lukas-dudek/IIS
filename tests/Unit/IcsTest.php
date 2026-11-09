<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IcsTest extends TestCase
{
    public function testEscapeAndDatetime(): void
    {
        self::assertSame('A\\, B\\; C\\\\ D\\nE', ics_escape("A, B; C\\ D\nE"));
        self::assertSame('20261120T180000Z', ics_datetime('2026-11-20 18:00:00'));
    }

    public function testBuildCalendarWithStableUidAndEscapedText(): void
    {
        $events = [[
            'id' => 5,
            'slug' => 'test-akce',
            'title' => 'Test, akce; 1',
            'description' => "Popis\ns novým řádkem",
            'starts_at' => '2026-11-20 18:00:00',
            'ends_at' => '2026-11-20 21:00:00',
            'venue_name' => 'Divadlo',
            'venue_street' => 'Ulice 1',
            'venue_postal_code' => '602 00',
            'venue_city' => 'Brno',
            'category_name' => 'Koncert',
        ]];

        $ics = build_ics($events, 'iis.local', 'http://example.test', '2026-11-09 12:00:00');

        self::assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("UID:event-5@iis.local\r\n", $ics);
        self::assertStringContainsString("DTSTART:20261120T180000Z\r\n", $ics);
        self::assertStringContainsString("DTEND:20261120T210000Z\r\n", $ics);
        self::assertStringContainsString("SUMMARY:Test\\, akce\\; 1\r\n", $ics);
        self::assertStringContainsString("URL:http://example.test/udalosti/test-akce\r\n", $ics);
        self::assertStringContainsString('Popis\\ns novým řádkem', $ics);
        self::assertStringContainsString('LOCATION:Divadlo Ulice 1 602 00 Brno', $ics);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        self::assertSame(0, preg_match('/[^\r]\n/', $ics));
    }

    public function testFoldingKeepsLinesWithinLimit(): void
    {
        $ics = build_ics([[
            'id' => 1,
            'slug' => 'dlouha-akce',
            'title' => str_repeat('Velmi dlouhý název události ', 10),
            'description' => str_repeat('Popis ', 30),
            'starts_at' => '2026-11-20 18:00:00',
            'ends_at' => '2026-11-20 21:00:00',
        ]], 'iis.local', '', '2026-11-09 12:00:00');

        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, strlen($line));
        }
        self::assertStringContainsString("\r\n ", $ics);
    }

    public function testEmptyCalendar(): void
    {
        $ics = build_ics([], 'iis.local', '', '2026-11-09 12:00:00');

        self::assertStringContainsString("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("END:VCALENDAR\r\n", $ics);
        self::assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }
}
