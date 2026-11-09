<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessRuleException;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\TicketTypeRepository;
use App\Repositories\VenueRepository;
use App\Services\EventService;
use App\Services\RegistrationService;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?EventRepository $events = null;
    private ?TicketTypeRepository $tickets = null;
    private ?RegistrationRepository $registrations = null;
    private ?EventService $eventService = null;
    private ?RegistrationService $service = null;

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
        $this->registrations = new RegistrationRepository($this->database);
        $venues = new VenueRepository($this->database);
        $categories = new CategoryRepository($this->database);
        $this->eventService = new EventService($this->events, $this->tickets, $venues, $categories, $this->database);
        $this->service = new RegistrationService(
            $this->database,
            $this->events,
            $this->tickets,
            $this->registrations
        );
    }

    protected function tearDown(): void
    {
        $this->cleanTestEvents();
        $this->database = null;
        $this->events = null;
        $this->tickets = null;
        $this->registrations = null;
        $this->eventService = null;
        $this->service = null;
    }

    public function testFreeRegistrationIsConfirmed(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_free', 5, '0');

        $registration = $this->service->register($eventId, 4, $this->tickets->forEvent($eventId)[0]['id']);

        self::assertSame('confirmed', $registration['status']);
        self::assertNull($registration['variable_symbol']);
    }

    public function testPaidRegistrationIsPendingWithVariableSymbolAndPriceSnapshot(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_paid', 5, '100');
        $ticket = $this->tickets->forEvent($eventId)[0];

        $registration = $this->service->register($eventId, 4, (int) $ticket['id']);

        self::assertSame('pending', $registration['status']);
        self::assertSame('100.00', $registration['price']);
        self::assertSame(10, strlen((string) $registration['variable_symbol']));
        self::assertMatchesRegularExpression('/^\d{10}$/', (string) $registration['variable_symbol']);

        $this->database->pdo()->prepare('UPDATE ticket_types SET price = 200.00 WHERE id = ?')
            ->execute([(int) $ticket['id']]);
        self::assertSame('100.00', $this->registrations->find((int) $registration['id'])['price']);
    }

    public function testDuplicateActiveRegistrationIsRejected(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_duplicate', 5, '0');
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];
        $this->service->register($eventId, 4, $ticketId);

        $this->expectException(ValidationException::class);
        $this->service->register($eventId, 4, $ticketId);
    }

    public function testEventCapacityIsEnforced(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_event_capacity', 1, '0');
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];
        $this->service->register($eventId, 4, $ticketId);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Kapacita události je plná.');
        $this->service->register($eventId, 5, $ticketId);
    }

    public function testTicketTypeCapacityIsEnforced(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_ticket_capacity', null, '0', 1);
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];
        $this->service->register($eventId, 4, $ticketId);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Kapacita tohoto typu vstupenky je plná.');
        $this->service->register($eventId, 5, $ticketId);
    }

    public function testCancelThenReregisterReusesRow(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_reuse', 5, '0');
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];

        $first = $this->service->register($eventId, 4, $ticketId);
        $this->service->cancel((int) $first['id'], 4, false);
        self::assertSame('cancelled', $this->registrations->find((int) $first['id'])['status']);

        $second = $this->service->register($eventId, 4, $ticketId);
        self::assertSame((int) $first['id'], (int) $second['id']);
        self::assertSame('confirmed', $second['status']);
        self::assertNull($second['cancelled_at']);
    }

    public function testPaymentClaimConfirmAndReject(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_payments', 5, '100');
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];

        $first = $this->service->register($eventId, 4, $ticketId);
        $this->service->claimPayment((int) $first['id'], 4);
        self::assertNotNull($this->registrations->find((int) $first['id'])['payment_claimed_at']);
        $this->service->confirmPayment((int) $first['id'], 3, false);
        self::assertSame('confirmed', $this->registrations->find((int) $first['id'])['status']);

        $second = $this->service->register($eventId, 5, $ticketId);
        $this->service->rejectPayment((int) $second['id'], 3, false);
        self::assertSame('cancelled', $this->registrations->find((int) $second['id'])['status']);
    }

    public function testForeignUserCannotCancelOrConfirm(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_foreign', 5, '100');
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];
        $registration = $this->service->register($eventId, 4, $ticketId);

        try {
            $this->service->cancel((int) $registration['id'], 5, false);
            self::fail('Cizí uživatel neměl moct zrušit registraci.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }

        try {
            $this->service->confirmPayment((int) $registration['id'], 4, false);
            self::fail('Cizí uživatel neměl moct potvrdit platbu.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->status());
        }
    }

    public function testManagerCanCancelParticipantRegistration(): void
    {
        [$eventId] = $this->createApprovedEvent('test_registration_service_manager_cancel', 5, '0');
        $ticketId = (int) $this->tickets->forEvent($eventId)[0]['id'];
        $registration = $this->service->register($eventId, 4, $ticketId);

        $this->service->cancel((int) $registration['id'], 3, false);

        self::assertSame('cancelled', $this->registrations->find((int) $registration['id'])['status']);
    }

    private function createApprovedEvent(
        string $title,
        ?int $eventCapacity,
        string $price,
        ?int $ticketCapacity = null
    ): array {
        $data = [
            'title' => $title,
            'description' => 'Testovací událost pro registrace, dostatečně dlouhý popis.',
            'starts_at' => '2026-12-20 17:00:00',
            'ends_at' => '2026-12-20 19:00:00',
            'venue_id' => 3,
            'category_id' => 6,
            'capacity' => $eventCapacity,
            'payment_note' => 'Platba na účet 123/0100.',
        ];
        $ticketRows = [
            ['id' => null, 'name' => 'Vstup', 'price' => $price, 'capacity' => $ticketCapacity],
        ];

        $eventId = $this->eventService->create(3, $data, $ticketRows, false);
        $existing = [];
        foreach ($this->tickets->forEvent($eventId) as $ticket) {
            $existing[] = [
                'id' => (int) $ticket['id'],
                'name' => $ticket['name'],
                'price' => $ticket['price'],
                'capacity' => $ticket['capacity'] === null ? null : (int) $ticket['capacity'],
            ];
        }
        $this->eventService->save($eventId, 3, false, $data, $existing, true);
        $this->eventService->approve($eventId, 2);

        return [$eventId, $this->tickets->forEvent($eventId)];
    }

    private function cleanTestEvents(): void
    {
        if ($this->database === null) {
            return;
        }

        $ids = $this->database->pdo()
            ->query("SELECT id FROM events WHERE title LIKE 'test_registration_service%'")
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
