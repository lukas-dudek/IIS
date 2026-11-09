<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\EventRepository;
use PHPUnit\Framework\TestCase;

final class ModerationQueueTest extends TestCase
{
    private ?Database $database = null;
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
        $this->database = new Database($config);
        $this->cleanTestEvents();
        $this->events = new EventRepository($this->database);
    }

    protected function tearDown(): void
    {
        $this->cleanTestEvents();
        $this->database = null;
        $this->events = null;
    }

    public function testPendingQueueAndVenueTimeCollision(): void
    {
        $pendingId = $this->createEvent('test_moderation_queue_pending', '2026-12-22 18:00:00', '2026-12-22 20:00:00', 'pending');
        $approvedId = $this->createEvent('test_moderation_queue_approved', '2026-12-22 18:30:00', '2026-12-22 21:00:00', 'approved');

        $queue = $this->events->pendingForModeration();
        $queueIds = array_map('intval', array_column($queue, 'id'));
        self::assertContains($pendingId, $queueIds);
        self::assertNotContains($approvedId, $queueIds);

        $pending = $this->events->find($pendingId);
        $collisions = $this->events->collisionsForEvent($pending);
        self::assertCount(1, $collisions);
        self::assertSame($approvedId, (int) $collisions[0]['id']);
    }

    private function createEvent(string $slug, string $startsAt, string $endsAt, string $status): int
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO events (slug, title, description, starts_at, ends_at, venue_id, category_id,
                                 capacity, status, manager_id, submitted_at, created_at, updated_at)
             VALUES (?, ?, \'Testovací událost pro frontu moderace, dostatečně dlouhý popis.\', ?, ?, 3, 6,
                     10, ?, 3, ?, ?, ?)'
        );
        $statement->execute([$slug, $slug, $startsAt, $endsAt, $status, $now, $now, $now]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    private function cleanTestEvents(): void
    {
        if ($this->database === null) {
            return;
        }

        $this->database->pdo()->exec("DELETE FROM events WHERE slug LIKE 'test_moderation_queue%'");
    }
}
