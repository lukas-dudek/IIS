<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Repositories\CommentRepository;
use App\Repositories\EventRepository;
use App\Repositories\RatingRepository;
use App\Repositories\TicketTypeRepository;
use PHPUnit\Framework\TestCase;

final class EventSearchTest extends TestCase
{
    private ?EventRepository $events = null;
    private ?TicketTypeRepository $tickets = null;
    private ?CommentRepository $comments = null;
    private ?RatingRepository $ratings = null;

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
        $database = new Database($config);
        $this->events = new EventRepository($database);
        $this->tickets = new TicketTypeRepository($database);
        $this->comments = new CommentRepository($database);
        $this->ratings = new RatingRepository($database);
    }

    public function testDefaultSearchReturnsOnlyFutureApprovedEvents(): void
    {
        $result = $this->events->search(['archive' => false, 'sort' => 'date'], 1, 12);

        self::assertSame(1, $result['total']);
        self::assertSame('seminar-moderni-php', $result['items'][0]['slug']);
    }

    public function testArchiveIncludesPastApprovedEvents(): void
    {
        $result = $this->events->search(['archive' => true, 'sort' => 'date'], 1, 12);

        self::assertSame(2, $result['total']);
        $slugs = array_column($result['items'], 'slug');
        self::assertContains('koncert-komorni-hudby', $slugs);
        self::assertContains('seminar-moderni-php', $slugs);
    }

    public function testTextSearchHandlesDiacriticsAndEscapesLike(): void
    {
        self::assertSame(1, $this->events->search(['q' => 'Moderní'], 1, 12)['total']);
        self::assertSame(0, $this->events->search(['q' => '%'], 1, 12)['total']);
        self::assertSame(0, $this->events->search(['q' => "' OR 1=1 --"], 1, 12)['total']);
    }

    public function testCategoryPriceAvailabilityAndCityFilters(): void
    {
        self::assertSame(1, $this->events->search(['category_ids' => [5, 6]], 1, 12)['total']);
        self::assertSame(1, $this->events->search(['archive' => true, 'price' => 'free'], 1, 12)['total']);
        self::assertSame(1, $this->events->search(['price' => 'paid'], 1, 12)['total']);
        self::assertSame(1, $this->events->search(['availability' => 'available'], 1, 12)['total']);
        self::assertSame(0, $this->events->search(['availability' => 'full'], 1, 12)['total']);
        self::assertSame(1, $this->events->search(['city' => 'Brno'], 1, 12)['total']);
        self::assertContains('Brno', $this->events->cities());
    }

    public function testPaginationKeepsTotalAndSplitsItems(): void
    {
        $first = $this->events->search(['archive' => true, 'sort' => 'date'], 1, 1);
        $second = $this->events->search(['archive' => true, 'sort' => 'date'], 2, 1);

        self::assertSame(2, $first['total']);
        self::assertCount(1, $first['items']);
        self::assertCount(1, $second['items']);
        self::assertNotSame($first['items'][0]['id'], $second['items'][0]['id']);
    }

    public function testPublicDetailOnlyForApprovedEvents(): void
    {
        self::assertNotNull($this->events->findBySlug('seminar-moderni-php'));
        self::assertNull($this->events->findBySlug('prednaska-o-bezpecnosti'));
        self::assertNull($this->events->findBySlug('workshop-testovani'));
    }

    public function testTicketAvailabilityCommentsAndRatings(): void
    {
        $event = $this->events->findBySlug('seminar-moderni-php');
        $tickets = $this->tickets->forEventWithAvailability((int) $event['id']);
        self::assertCount(2, $tickets);
        self::assertSame(1, (int) $tickets[0]['active_count']);

        $comments = $this->comments->visibleForEvent(1);
        self::assertCount(2, $comments);
        self::assertSame('Jan Novák', $comments[0]['author_name']);

        $rating = $this->ratings->summaryForEvent(1);
        self::assertSame(2, (int) $rating['count']);
        self::assertEqualsWithDelta(4.5, (float) $rating['average'], 0.001);
    }
}
