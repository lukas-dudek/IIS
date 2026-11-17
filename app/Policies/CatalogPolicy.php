<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Models\Venue;

final class CatalogPolicy
{
    public function updateProposal(User $user, Venue|Category $record): bool
    {
        if ((int) $record->proposed_by !== $user->id) {
            return false;
        }

        return in_array($record->status, ['pending', 'rejected'], true);
    }

    public function delete(User $user, Venue|Category $record): bool
    {
        return $user->canModerate();
    }
}
