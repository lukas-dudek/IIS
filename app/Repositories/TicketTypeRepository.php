<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class TicketTypeRepository
{
    public function __construct(private Database $database)
    {
    }

    public function forEvent(int $eventId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM ticket_types WHERE event_id = ? ORDER BY id ASC'
        );
        $statement->execute([$eventId]);

        return $statement->fetchAll();
    }

    public function forEventWithAvailability(int $eventId): array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM registrations r
                     WHERE r.ticket_type_id = t.id AND r.status IN ('pending', 'confirmed')) AS active_count
             FROM ticket_types t
             WHERE t.event_id = ?
             ORDER BY t.price ASC, t.name ASC"
        );
        $statement->execute([$eventId]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM ticket_types WHERE id = ?');
        $statement->execute([$id]);
        $ticketType = $statement->fetch();

        return $ticketType === false ? null : $ticketType;
    }

    public function create(int $eventId, string $name, string $price, ?int $capacity): int
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO ticket_types (event_id, name, price, capacity, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$eventId, $name, $price, $capacity, $now, $now]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function update(int $id, string $name, string $price, ?int $capacity): void
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE ticket_types SET name = ?, price = ?, capacity = ?, updated_at = ? WHERE id = ?'
        );
        $statement->execute([$name, $price, $capacity, now_utc(), $id]);
    }

    public function deleteIfUnused(int $id): bool
    {
        $statement = $this->database->pdo()->prepare(
            'DELETE FROM ticket_types
             WHERE id = ?
               AND NOT EXISTS (SELECT 1 FROM registrations WHERE ticket_type_id = ?)'
        );
        $statement->execute([$id, $id]);

        return $statement->rowCount() > 0;
    }

    public function activeRegistrationCount(int $id): int
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT COUNT(*) FROM registrations
             WHERE ticket_type_id = ? AND status IN ('pending', 'confirmed')"
        );
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }
}
