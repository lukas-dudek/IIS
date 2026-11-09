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
}
