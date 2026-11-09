<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\CommentRepository;
use App\Repositories\EventRepository;
use App\Repositories\RatingRepository;
use App\Repositories\RegistrationRepository;

final class FeedbackService
{
    public function __construct(
        private EventRepository $events,
        private RegistrationRepository $registrations,
        private CommentRepository $comments,
        private RatingRepository $ratings
    ) {
    }

    public function saveComment(int $eventId, int $userId, string $body): int
    {
        $this->assertCanFeedback($eventId, $userId);
        $body = $this->validateBody($body);

        if ($this->comments->findForEventUser($eventId, $userId) !== null) {
            throw new BusinessRuleException('Komentář k této události už máte. Můžete ho upravit.');
        }

        return $this->comments->create($eventId, $userId, $body);
    }

    public function updateComment(int $commentId, int $userId, string $body): array
    {
        $comment = $this->comments->find($commentId);
        if ($comment === null) {
            throw new HttpException(404, 'Komentář nenalezen.');
        }
        if ((int) $comment['user_id'] !== $userId) {
            throw new HttpException(403, 'Komentář můžete upravit jen jako jeho autor.');
        }

        $this->comments->update($commentId, $this->validateBody($body));

        return $comment;
    }

    public function deleteComment(int $commentId, int $userId): array
    {
        $comment = $this->comments->find($commentId);
        if ($comment === null) {
            throw new HttpException(404, 'Komentář nenalezen.');
        }
        if ((int) $comment['user_id'] !== $userId) {
            throw new HttpException(403, 'Komentář můžete smazat jen jako jeho autor.');
        }

        $this->comments->delete($commentId);

        return $comment;
    }

    public function saveRating(int $eventId, int $userId, int $value): void
    {
        $this->assertCanFeedback($eventId, $userId);
        if ($value < 1 || $value > 5) {
            throw new ValidationException(['value' => 'Hodnocení musí být 1–5.']);
        }

        $this->ratings->upsert($eventId, $userId, $value);
    }

    public function hideComment(int $commentId, int $moderatorId, string $reason): void
    {
        $comment = $this->comments->find($commentId);
        if ($comment === null) {
            throw new HttpException(404, 'Komentář nenalezen.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'Důvod skrytí musí mít 3–255 znaků.']);
        }

        $this->comments->hide($commentId, $moderatorId, $reason);
    }

    public function showComment(int $commentId, int $moderatorId): void
    {
        if ($this->comments->find($commentId) === null) {
            throw new HttpException(404, 'Komentář nenalezen.');
        }

        $this->comments->show($commentId, $moderatorId);
    }

    private function assertCanFeedback(int $eventId, int $userId): array
    {
        $event = $this->events->find($eventId);
        if ($event === null) {
            throw new HttpException(404, 'Událost nenalezena.');
        }
        if ($event['status'] !== 'approved') {
            throw new BusinessRuleException('Hodnotit a komentovat lze jen schválenou událost.');
        }
        if (!is_past((string) $event['ends_at'])) {
            throw new BusinessRuleException('Hodnotit a komentovat lze až po ukončení události.');
        }

        $registration = $this->registrations->findForEventUser($eventId, $userId);
        if ($registration === null || $registration['status'] !== 'confirmed') {
            throw new BusinessRuleException('Hodnotit a komentovat mohou jen potvrzení účastníci.');
        }

        return $event;
    }

    private function validateBody(string $body): string
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new ValidationException(['body' => 'Komentář musí mít 1–2000 znaků.']);
        }

        return $body;
    }
}
