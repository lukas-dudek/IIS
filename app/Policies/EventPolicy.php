<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    public function manage(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->manager_id === $user->id;
    }

    public function submit(User $user, Event $event): bool
    {
        return $this->manage($user, $event)
            && in_array($event->status, [Event::STATUS_DRAFT, Event::STATUS_REJECTED], true);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $this->manage($user, $event)
            && in_array($event->status, [Event::STATUS_DRAFT, Event::STATUS_PENDING, Event::STATUS_APPROVED], true);
    }

    public function moderate(User $user, Event $event): bool
    {
        return $user->canModerate() && $event->manager_id !== $user->id;
    }

    public function viewParticipants(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->manager_id === $user->id;
    }
}
