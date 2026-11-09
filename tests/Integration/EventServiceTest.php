<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessRuleException;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use App\Repositories\EventRepository;
use App\Repositories\TicketTypeRepository;
use App\Repositories\VenueRepository;
use App\Services\EventService;
use PHPUnit\Framework\TestCase;

final class EventServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?EventRepository $events = null;
    private ?TicketTypeRepository $tickets = null;
    private ?EventService $service = null;

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
        $this->tickets = new TicketTypeRepository($this->database);
        $this->service = new EventService(
            $this->events,
            $this->tickets,
            new VenueRepository($this->database),
            new CategoryRepository($this->database),
            $this->database
        );
    }

    protected function tearDown(): void
    {
        $this->cleanTestEvents();
        $this->database = null;
        $this->events = null;
        $this->tickets = null;
        $this->service = null;
    }

    public function testDraftCanHaveNoTickets(): void
    {
        $id = $this->service->create(3, $this->eventData('test_event_service_draft'), [], false);
        $event = $this->events->find($id);

        self::assertSame('draft', $event['status']);
        self::assertSame([], $this->tickets->forEvent($id));
    }

    public function testSubmitRequiresTicketAndFutureStart(): void
    {
        $id = $this->service->create(3, $this->eventData('test_event_service_submit'), [], false);

        try {
            $this->service->save($id, 3, false, $this->eventData('test_event_service_submit'), [], true);
            self::fail('Odeslání bez vstupenky mělo selhat.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('tickets', $exception->errors());
        }

        $past = $this->eventData('test_event_service_submit');
        $past['starts_at'] = '2020-01-01 18:00:00';
        $past['ends_at'] = '2020-01-01 20:00:00';
        try {
            $this->service->save($id, 3, false, $past, $this->ticketRows(), true);
            self::fail('Odeslání události v minulosti mělo selhat.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('starts_at', $exception->errors());
        }

        $this->service->save($id, 3, false, $this->eventData('test_event_service_submit'), $this->ticketRows(), true);
        $event = $this->events->find($id);
        self::assertSame('pending', $event['status']);
        self::assertNotNull($event['submitted_at']);
    }

    public function testSignificantChangeOfApprovedEventReturnsToPending(): void
    {
        $id = $this->createApprovedEvent('test_event_service_significant');
        $descriptionOnly = $this->eventData('test_event_service_significant');
        $descriptionOnly['description'] = 'Změněný popis schválené události, stále dostatečně dlouhý.';

        $this->service->save($id, 3, false, $descriptionOnly, $this->ticketRowsForEvent($id), false);
        self::assertSame('approved', $this->events->find($id)['status']);

        $titleChange = $this->eventData('test_event_service_significant_changed');
        $this->service->save($id, 3, false, $titleChange, $this->ticketRowsForEvent($id), false);

        $event = $this->events->find($id);
        self::assertSame('pending', $event['status']);
        self::assertNull($event['reviewed_by']);
        self::assertNotNull($event['submitted_at']);
    }

    public function testManagerCannotApproveOwnEvent(): void
    {
        $id = $this->createPendingEvent('test_event_service_own_approval');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Správce události ji nemůže sám schválit.');
        $this->service->approve($id, 3);
    }

    public function testRejectAndResubmitEvent(): void
    {
        $id = $this->createPendingEvent('test_event_service_reject');
        $this->service->reject($id, 2, 'Nevhodná událost.');

        $rejected = $this->events->find($id);
        self::assertSame('rejected', $rejected['status']);
        self::assertSame('Nevhodná událost.', $rejected['rejection_reason']);

        $this->service->save(
            $id,
            3,
            false,
            $this->eventData('test_event_service_reject_updated'),
            $this->ticketRowsForEvent($id),
            true
        );

        $pending = $this->events->find($id);
        self::assertSame('pending', $pending['status']);
        self::assertNull($pending['rejection_reason']);
    }

    public function testCannotSubmitPendingEvent(): void
    {
        $id = $this->createPendingEvent('test_event_service_pending_submit');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Odeslat lze jen koncept nebo zamítnutou událost.');
        $this->service->save($id, 3, false, $this->eventData('test_event_service_pending_submit'), $this->ticketRowsForEvent($id), true);
    }

    public function testCannotEditCancelledEvent(): void
    {
        $id = $this->createApprovedEvent('test_event_service_cancelled');
        $this->service->cancel($id, 3, false, 'Ruším testovací událost.');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Zrušenou událost nelze upravovat.');
        $this->service->save(
            $id,
            3,
            false,
            $this->eventData('test_event_service_cancelled'),
            $this->ticketRowsForEvent($id),
            false
        );
    }

    public function testCapacityAndTicketRulesWithRegistrations(): void
    {
        $id = $this->createApprovedEvent('test_event_service_capacity');
        $ticket = $this->tickets->forEvent($id)[0];
        $this->register($id, (int) $ticket['id'], 4);
        $this->register($id, (int) $ticket['id'], 5);

        $tooSmall = $this->eventData('test_event_service_capacity');
        $tooSmall['capacity'] = 1;
        try {
            $this->service->save($id, 3, false, $tooSmall, $this->ticketRowsForEvent($id), false);
            self::fail('Snížení kapacity pod registrace mělo selhat.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('capacity', $exception->errors());
        }

        $ticketTooSmall = $this->ticketRowsForEvent($id);
        $ticketTooSmall[0]['capacity'] = 1;
        try {
            $this->service->save($id, 3, false, $this->eventData('test_event_service_capacity'), $ticketTooSmall, false);
            self::fail('Snížení kapacity typu pod registrace mělo selhat.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('ticket_capacity_0', $exception->errors());
        }

        $removeTicket = $this->ticketRowsForEvent($id);
        $removeTicket[0]['name'] = '';
        try {
            $this->service->save($id, 3, false, $this->eventData('test_event_service_capacity'), $removeTicket, false);
            self::fail('Smazání typu s registracemi mělo selhat.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('ticket_name_0', $exception->errors());
        }
    }

    public function testCancelEventCancelsActiveRegistrations(): void
    {
        $id = $this->createApprovedEvent('test_event_service_cancel');
        $ticket = $this->tickets->forEvent($id)[0];
        $this->register($id, (int) $ticket['id'], 4);

        $this->service->cancel($id, 3, false, 'Ruším testovací událost.');

        self::assertSame('cancelled', $this->events->find($id)['status']);
        $status = $this->database->pdo()
            ->query('SELECT status FROM registrations WHERE event_id = ' . $id)
            ->fetchColumn();
        self::assertSame('cancelled', $status);
    }

    public function testSubmitWithPendingVenueFails(): void
    {
        $id = $this->service->create(3, $this->eventData('test_event_service_venue'), $this->ticketRows(), false);
        $data = $this->eventData('test_event_service_venue');
        $data['venue_id'] = 4;

        $this->expectException(ValidationException::class);
        $this->service->save($id, 3, false, $data, $this->ticketRowsForEvent($id), true);
    }

    public function testSlugCollisionGetsSuffix(): void
    {
        $first = $this->service->create(3, $this->eventData('test_event_service_slug'), [], false);
        $second = $this->service->create(3, $this->eventData('test_event_service_slug'), [], false);

        self::assertSame('test-event-service-slug', $this->events->find($first)['slug']);
        self::assertSame('test-event-service-slug-2', $this->events->find($second)['slug']);
    }

    private function createPendingEvent(string $name): int
    {
        $id = $this->service->create(3, $this->eventData($name), $this->ticketRows(), false);
        $this->service->save($id, 3, false, $this->eventData($name), $this->ticketRowsForEvent($id), true);

        return $id;
    }

    private function createApprovedEvent(string $name): int
    {
        $id = $this->createPendingEvent($name);
        $this->service->approve($id, 2);

        return $id;
    }

    private function eventData(string $title): array
    {
        return [
            'title' => $title,
            'description' => 'Testovací událost pro integrační testy služby, dostatečně dlouhý popis.',
            'starts_at' => '2026-12-20 17:00:00',
            'ends_at' => '2026-12-20 19:00:00',
            'venue_id' => 3,
            'category_id' => 6,
            'capacity' => 10,
            'payment_note' => 'Platba na účet 123/0100.',
        ];
    }

    private function ticketRows(): array
    {
        return [
            ['id' => null, 'name' => 'Základní', 'price' => '100', 'capacity' => 10],
            ['id' => null, 'name' => 'Student', 'price' => '50', 'capacity' => null],
        ];
    }

    private function ticketRowsForEvent(int $eventId): array
    {
        $rows = [];
        foreach ($this->tickets->forEvent($eventId) as $ticket) {
            $rows[] = [
                'id' => (int) $ticket['id'],
                'name' => $ticket['name'],
                'price' => $ticket['price'],
                'capacity' => $ticket['capacity'] === null ? null : (int) $ticket['capacity'],
            ];
        }

        return $rows;
    }

    private function register(int $eventId, int $ticketTypeId, int $userId): void
    {
        $statement = $this->database->pdo()->prepare(
            "INSERT INTO registrations (event_id, user_id, ticket_type_id, status, price, registered_at)
             VALUES (?, ?, ?, 'confirmed', 100.00, ?)"
        );
        $statement->execute([$eventId, $userId, $ticketTypeId, now_utc()]);
    }

    private function cleanTestEvents(): void
    {
        if ($this->database === null) {
            return;
        }

        $ids = $this->database->pdo()
            ->query("SELECT id FROM events WHERE title LIKE 'test_event_service%'")
            ->fetchAll(\PDO::FETCH_COLUMN);
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->database->pdo()->prepare('DELETE FROM registrations WHERE event_id IN (' . $placeholders . ')')
            ->execute($ids);
        $this->database->pdo()->prepare('DELETE FROM events WHERE id IN (' . $placeholders . ')')
            ->execute($ids);
    }
}
