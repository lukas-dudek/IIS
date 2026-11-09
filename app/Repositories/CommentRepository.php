<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CommentRepository
{
    public function __construct(private Database $database)
    {
    }

    public function visibleForEvent(int $eventId): array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT c.*, u.name AS author_name
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.event_id = ? AND c.status = 'visible'
             ORDER BY c.created_at ASC"
        );
        $statement->execute([$eventId]);

        return $statement->fetchAll();
    }
}
