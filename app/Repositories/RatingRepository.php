<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class RatingRepository
{
    public function __construct(private Database $database)
    {
    }

    public function summaryForEvent(int $eventId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT COALESCE(AVG(value), 0) AS average, COUNT(*) AS count FROM ratings WHERE event_id = ?'
        );
        $statement->execute([$eventId]);
        $summary = $statement->fetch();

        return $summary === false ? ['average' => 0, 'count' => 0] : $summary;
    }

    public function findForEventUser(int $eventId, int $userId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM ratings WHERE event_id = ? AND user_id = ?'
        );
        $statement->execute([$eventId, $userId]);
        $rating = $statement->fetch();

        return $rating === false ? null : $rating;
    }

    public function upsert(int $eventId, int $userId, int $value): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO ratings (event_id, user_id, value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
        );
        $statement->execute([$eventId, $userId, $value, $now, $now]);
    }
}
