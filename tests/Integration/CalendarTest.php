<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\EventRepository;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    private ?EventRepository $events = null;

    protected function setUp(): void
    {
        $password = getenv('IIS_DB_PASSWORD');
        if ($password === false || $password === '') {
            self::markTestSkipped('Pro integrační test nastav IIS_DB_PASSWORD.');
        }

        $config = new Config([
            'db' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'iis_test',
                'user' => 'iis',
                'password' => $password,
                'charset' => 'utf8mb4',
            ],
        ]);
        $this->events = new EventRepository(new Database($config));
    }

    public function testCalendarIncludesConfirmedParticipantAndManagedEvents(): void
    {
        $participant = $this->events->calendarForUser(4, '2026-10-01 00:00:00', '2026-10-31 23:59:59');
        self::assertCount(2, $participant);
        $participantSlugs = array_column($participant, 'slug');
        self::assertContains('koncert-komorni-hudby', $participantSlugs);
        self::assertContains('workshop-ux-designu', $participantSlugs);
        foreach ($participant as $event) {
            self::assertSame('confirmed', $event['registration_status']);
        }

        $manager = $this->events->calendarForUser(4, '2026-11-01 00:00:00', '2026-11-30 23:59:59');
        self::assertNotEmpty($manager);
        $slugs = array_column($manager, 'slug');
        self::assertContains('seminar-moderni-php', $slugs);
        self::assertNotContains('letni-kino', $slugs);
    }

    public function testExportIncludesOnlyActiveManagedOrConfirmedEvents(): void
    {
        $managed = $this->events->exportForUser(4);
        $slugs = array_column($managed, 'slug');
        self::assertContains('seminar-moderni-php', $slugs);
        self::assertNotContains('koncert-komorni-hudby', $slugs);
        self::assertNotContains('letni-kino', $slugs);

        self::assertSame([], $this->events->exportForUser(10));
    }
}
