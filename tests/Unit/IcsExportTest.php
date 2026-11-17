<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\IcsExport;
use App\Support\Slug;
use App\Support\Czech;
use Tests\TestCase;

final class IcsExportTest extends TestCase
{
    public function testEscapesReservedCharacters(): void
    {
        $this->assertSame('A\\, B\\; C\\\\ D\\nE', IcsExport::escape("A, B; C\\ D\nE"));
    }

    public function testFoldKeepsLinesWithinLimit(): void
    {
        $line = str_repeat('á', 120);
        $folded = IcsExport::fold($line);

        foreach (explode("\r\n", $folded) as $part) {
            $this->assertLessThanOrEqual(75, strlen(ltrim($part, ' ')));
        }
        $this->assertSame($line, str_replace(["\r\n", ' '], '', $folded));
    }

    public function testShortLineIsNotFolded(): void
    {
        $this->assertSame('SUMMARY:Krátký název', IcsExport::fold('SUMMARY:Krátký název'));
    }

    public function testBuilderProducesValidCalendarEnvelope(): void
    {
        $ics = IcsExport::build([], 'iis.test', 'https://iis.test');

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('X-WR-TIMEZONE:Europe/Prague', $ics);
    }

    public function testCzechPluralSelectsCorrectForm(): void
    {
        $this->assertSame('událost', Czech::plural(1, 'událost', 'události', 'událostí'));
        $this->assertSame('události', Czech::plural(3, 'událost', 'události', 'událostí'));
        $this->assertSame('událostí', Czech::plural(11, 'událost', 'události', 'událostí'));
        $this->assertSame('událostí', Czech::plural(0, 'událost', 'události', 'událostí'));
    }

    public function testSlugStripsDiacritics(): void
    {
        $this->assertSame('prednaska-o-umele-inteligenci', Slug::make('Přednáška o umělé inteligenci', 180));
        $this->assertSame('polozka', Slug::make('!!!', 180));
    }
}
