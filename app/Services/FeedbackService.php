<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Rating;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class FeedbackService
{
    public function saveComment(Event $event, User $user, string $body): Comment
    {
        $this->assertCanGiveFeedback($event, $user);
        $body = $this->validateBody($body);

        $existing = Comment::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['body' => $body, 'status' => Comment::STATUS_VISIBLE])->save();

            return $existing;
        }

        return Comment::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => Comment::STATUS_VISIBLE,
        ]);
    }

    public function updateComment(Comment $comment, User $user, string $body): Comment
    {
        if ($comment->user_id !== $user->id) {
            throw new AuthorizationException('Komentář můžete upravit jen jako jeho autor.');
        }

        $comment->forceFill(['body' => $this->validateBody($body)])->save();

        return $comment;
    }

    public function deleteComment(Comment $comment, User $user): void
    {
        if ($comment->user_id !== $user->id) {
            throw new AuthorizationException('Komentář můžete smazat jen jako jeho autor.');
        }

        $comment->delete();
    }

    public function saveRating(Event $event, User $user, int $value): Rating
    {
        $this->assertCanGiveFeedback($event, $user);
        if ($value < 1 || $value > 5) {
            throw ValidationException::withMessages(['value' => 'Hodnocení musí být 1–5.']);
        }

        return Rating::query()->updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['value' => $value]
        );
    }

    public function hideComment(Comment $comment, User $moderator, string $reason): Comment
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Důvod skrytí musí mít 3–255 znaků.']);
        }

        $comment->forceFill([
            'status' => Comment::STATUS_HIDDEN,
            'moderated_by' => $moderator->id,
            'moderated_at' => utc_now(),
            'hidden_reason' => $reason,
        ])->save();

        return $comment;
    }

    public function showComment(Comment $comment, User $moderator): Comment
    {
        $comment->forceFill([
            'status' => Comment::STATUS_VISIBLE,
            'moderated_by' => $moderator->id,
            'moderated_at' => utc_now(),
            'hidden_reason' => null,
        ])->save();

        return $comment;
    }

    public function canGiveFeedback(Event $event, User $user): bool
    {
        if (!$event->isApproved() || !$event->isFinished()) {
            return false;
        }

        return Registration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', Registration::STATUS_CONFIRMED)
            ->exists();
    }

    private function assertCanGiveFeedback(Event $event, User $user): void
    {
        if (!$event->isApproved()) {
            throw new DomainRuleException('Hodnotit a komentovat lze jen schválenou událost.');
        }
        if (!$event->isFinished()) {
            throw new DomainRuleException('Hodnotit a komentovat lze až po ukončení události.');
        }
        if (!$this->canGiveFeedback($event, $user)) {
            throw new DomainRuleException('Hodnotit a komentovat mohou jen potvrzení účastníci.');
        }
    }

    private function validateBody(string $body): string
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => 'Komentář musí mít 1–2000 znaků.']);
        }

        return $body;
    }
}
