<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessRuleException;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\CommentRepository;
use App\Repositories\EventRepository;
use App\Repositories\RatingRepository;
use App\Repositories\RegistrationRepository;
use App\Services\FeedbackService;
use PHPUnit\Framework\TestCase;

final class FeedbackServiceTest extends TestCase
{
    private ?Database $database = null;
    private ?CommentRepository $comments = null;
    private ?RatingRepository $ratings = null;
    private ?FeedbackService $service = null;

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
        $this->comments = new CommentRepository($this->database);
        $this->ratings = new RatingRepository($this->database);
        $this->service = new FeedbackService(
            new EventRepository($this->database),
            new RegistrationRepository($this->database),
            $this->comments,
            $this->ratings
        );
    }

    protected function tearDown(): void
    {
        $this->cleanTestEvents();
        $this->database = null;
        $this->comments = null;
        $this->ratings = null;
        $this->service = null;
    }

    public function testFutureEventCannotBeRated(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Hodnotit a komentovat lze až po ukončení události.');
        $this->service->saveRating(2, 3, 5);
    }

    public function testNonParticipantCannotComment(): void
    {
        $eventId = $this->createPastEvent('test_feedback_service_nonparticipant');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Hodnotit a komentovat mohou jen potvrzení účastníci.');
        $this->service->saveComment($eventId, 5, 'Komentář bez registrace.');
    }

    public function testCommentCreateUpdateDeleteAndDuplicate(): void
    {
        $eventId = $this->createPastEvent('test_feedback_service_comment');
        $commentId = $this->service->saveComment($eventId, 4, 'První komentář.');
        self::assertSame('První komentář.', $this->comments->find($commentId)['body']);

        $this->service->updateComment($commentId, 4, 'Upravený komentář.');
        self::assertSame('Upravený komentář.', $this->comments->find($commentId)['body']);

        try {
            $this->service->saveComment($eventId, 4, 'Další komentář.');
            self::fail('Druhý komentář měl selhat.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('už máte', $exception->getMessage());
        }

        $this->service->deleteComment($commentId, 4);
        self::assertNull($this->comments->find($commentId));
    }

    public function testRatingUpsertUpdatesExistingRating(): void
    {
        $eventId = $this->createPastEvent('test_feedback_service_rating');

        $this->service->saveRating($eventId, 4, 5);
        self::assertSame(5, (int) $this->ratings->findForEventUser($eventId, 4)['value']);

        $this->service->saveRating($eventId, 4, 4);
        $summary = $this->ratings->summaryForEvent($eventId);
        self::assertSame(1, (int) $summary['count']);
        self::assertEqualsWithDelta(4.0, (float) $summary['average'], 0.001);
    }

    public function testHideAndShowComment(): void
    {
        $eventId = $this->createPastEvent('test_feedback_service_hide');
        $commentId = $this->service->saveComment($eventId, 4, 'Komentář ke skrytí.');

        $this->service->hideComment($commentId, 2, 'Nevhodný obsah.');
        self::assertSame([], $this->comments->visibleForEvent($eventId));

        $hidden = $this->comments->forModeration('hidden', $eventId);
        self::assertCount(1, $hidden);
        self::assertSame('Nevhodný obsah.', $hidden[0]['hidden_reason']);

        $this->service->showComment($commentId, 2);
        self::assertCount(1, $this->comments->visibleForEvent($eventId));
    }

    public function testHideRequiresReason(): void
    {
        $eventId = $this->createPastEvent('test_feedback_service_reason');
        $commentId = $this->service->saveComment($eventId, 4, 'Komentář.');

        $this->expectException(ValidationException::class);
        $this->service->hideComment($commentId, 2, '');
    }

    public function testForeignUserCannotEditOrDeleteComment(): void
    {
        $eventId = $this->createPastEvent('test_feedback_service_foreign');
        $commentId = $this->service->saveComment($eventId, 4, 'Komentář.');

        try {
            $this->service->updateComment($commentId, 5, 'Hack');
            self::fail('Cizí uživatel neměl moct upravit komentář.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }

        try {
            $this->service->deleteComment($commentId, 5);
            self::fail('Cizí uživatel neměl moct smazat komentář.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }
    }

    private function createPastEvent(string $title): int
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "INSERT INTO events (slug, title, description, starts_at, ends_at, venue_id, category_id,
                                 capacity, status, manager_id, created_at, updated_at)
             VALUES (?, ?, 'Testovací událost pro komentáře, dostatečně dlouhý popis.',
                     '2026-10-01 17:00:00', '2026-10-01 19:00:00', 3, 6, 10, 'approved', 3, ?, ?)"
        );
        $statement->execute([slugify($title, 180), $title, $now, $now]);
        $eventId = (int) $this->database->pdo()->lastInsertId();

        $statement = $this->database->pdo()->prepare(
            'INSERT INTO ticket_types (event_id, name, price, capacity, created_at, updated_at)
             VALUES (?, ?, 0.00, NULL, ?, ?)'
        );
        $statement->execute([$eventId, 'Vstup', $now, $now]);
        $ticketId = (int) $this->database->pdo()->lastInsertId();

        $statement = $this->database->pdo()->prepare(
            "INSERT INTO registrations (event_id, user_id, ticket_type_id, status, price, registered_at)
             VALUES (?, 4, ?, 'confirmed', 0.00, ?)"
        );
        $statement->execute([$eventId, $ticketId, $now]);

        return $eventId;
    }

    private function cleanTestEvents(): void
    {
        if ($this->database === null) {
            return;
        }

        $ids = $this->database->pdo()
            ->query("SELECT id FROM events WHERE title LIKE 'test_feedback_service%'")
            ->fetchAll(\PDO::FETCH_COLUMN);
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach (['ratings', 'comments', 'registrations'] as $table) {
            $this->database->pdo()->prepare('DELETE FROM ' . $table . ' WHERE event_id IN (' . $placeholders . ')')
                ->execute($ids);
        }
        $this->database->pdo()->prepare('DELETE FROM events WHERE id IN (' . $placeholders . ')')
            ->execute($ids);
    }
}
