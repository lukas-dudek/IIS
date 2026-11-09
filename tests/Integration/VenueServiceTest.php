<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessRuleException;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Upload;
use App\Repositories\VenueRepository;
use App\Services\VenueService;
use PHPUnit\Framework\TestCase;

final class VenueServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?VenueRepository $venues = null;
    private ?VenueService $service = null;

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
        $this->database->pdo()->exec("DELETE FROM venues WHERE name LIKE 'test_venue_service%'");
        $this->venues = new VenueRepository($this->database);
        $this->service = new VenueService(
            $this->venues,
            new Upload(sys_get_temp_dir() . '/iis-test-uploads', 2 * 1024 * 1024)
        );
    }

    protected function tearDown(): void
    {
        $this->database?->pdo()->exec("DELETE FROM venues WHERE name LIKE 'test_venue_service%'");
        $this->database = null;
        $this->venues = null;
        $this->service = null;
    }

    public function testProposeApproveAndDelete(): void
    {
        $id = $this->service->propose($this->venueData('test_venue_service_one'), null, 3);
        $venue = $this->venues->find($id);
        self::assertNotNull($venue);
        self::assertSame('pending', $venue['status']);

        $this->service->approve($id, 2);
        $approved = $this->venues->find($id);
        self::assertSame('approved', $approved['status']);
        self::assertSame(2, (int) $approved['reviewed_by']);

        self::assertTrue($this->service->delete($id));
        self::assertNull($this->venues->find($id));
    }

    public function testForeignProposalCannotBeUpdated(): void
    {
        $id = $this->service->propose($this->venueData('test_venue_service_foreign'), null, 3);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Místo nenalezeno.');
        $this->service->updateProposal($id, 4, $this->venueData('test_venue_service_foreign'), null);
    }

    public function testApprovedVenueCannotBeUpdatedByProposer(): void
    {
        $id = $this->service->propose($this->venueData('test_venue_service_approved'), null, 3);
        $this->service->approve($id, 2);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Schválené místo může upravovat jen moderátor.');
        $this->service->updateProposal($id, 3, $this->venueData('test_venue_service_approved'), null);
    }

    public function testCannotRejectApprovedVenue(): void
    {
        $id = $this->service->propose($this->venueData('test_venue_service_reject'), null, 3);
        $this->service->approve($id, 2);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Místo už není ve stavu čeká na schválení.');
        $this->service->reject($id, 2, 'Zamítnutí po schválení.');
    }

    public function testUsedVenueCannotBeDeleted(): void
    {
        self::assertFalse($this->service->delete(1));
    }

    public function testRejectedProposalReturnsToPendingAfterUpdate(): void
    {
        $id = $this->service->propose($this->venueData('test_venue_service_resubmit'), null, 3);
        $this->service->reject($id, 2, 'Chybí údaje.');

        $rejected = $this->venues->find($id);
        self::assertSame('rejected', $rejected['status']);
        self::assertSame('Chybí údaje.', $rejected['rejection_reason']);

        $this->service->updateProposal($id, 3, $this->venueData('test_venue_service_resubmit'), null);

        $pending = $this->venues->find($id);
        self::assertSame('pending', $pending['status']);
        self::assertNull($pending['rejection_reason']);
        self::assertNull($pending['reviewed_by']);
    }

    private function venueData(string $name): array
    {
        return [
            'name' => $name,
            'street' => 'Testovací 1',
            'city' => 'Brno',
            'postal_code' => '602 00',
            'description' => 'Testovací místo.',
        ];
    }
}
