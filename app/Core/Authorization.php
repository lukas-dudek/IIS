<?php

declare(strict_types=1);

namespace App\Core;

final class Authorization
{
    public function __construct(private Auth $auth)
    {
    }

    public function isAdmin(): bool
    {
        return $this->auth->hasRole('admin');
    }

    public function isModerator(): bool
    {
        return $this->auth->hasRole('moderator');
    }

    public function canManageEvent(array $event): bool
    {
        $user = $this->auth->user();
        if ($user === null) {
            return false;
        }

        return $user['role'] === 'admin' || (int) $event['manager_id'] === (int) $user['id'];
    }
}
