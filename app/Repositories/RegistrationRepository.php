<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class RegistrationRepository
{
    public function __construct(private Database $database)
    {
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT r.*, e.title, e.slug, e.starts_at, e.ends_at, e.payment_note,
                    e.manager_id, e.capacity AS event_capacity,
                    v.name AS venue_name, v.city AS venue_city,
                    t.name AS ticket_name, u.name AS user_name, u.email AS user_email
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             JOIN venues v ON v.id = e.venue_id
             JOIN ticket_types t ON t.id = r.ticket_type_id
             JOIN users u ON u.id = r.user_id
             WHERE r.id = ?'
        );
        $statement->execute([$id]);
        $registration = $statement->fetch();

        return $registration === false ? null : $registration;
    }

    public function findForManager(int $id, int $managerId, bool $isAdmin): ?array
    {
        $registration = $this->find($id);
        if ($registration === null) {
            return null;
        }
        if ($isAdmin || (int) $registration['manager_id'] === $managerId) {
            return $registration;
        }

        return null;
    }

    public function findForEventUser(int $eventId, int $userId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM registrations WHERE event_id = ? AND user_id = ?'
        );
        $statement->execute([$eventId, $userId]);
        $registration = $statement->fetch();

        return $registration === false ? null : $registration;
    }

    public function forUser(int $userId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT r.*, e.title, e.slug, e.starts_at, e.ends_at, e.payment_note,
                    v.name AS venue_name, v.city AS venue_city, t.name AS ticket_name
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             JOIN venues v ON v.id = e.venue_id
             JOIN ticket_types t ON t.id = r.ticket_type_id
             WHERE r.user_id = ?
             ORDER BY e.starts_at DESC'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function forEvent(int $eventId, ?string $status): array
    {
        $sql = 'SELECT r.*, t.name AS ticket_name, u.name AS user_name, u.email AS user_email
                FROM registrations r
                JOIN ticket_types t ON t.id = r.ticket_type_id
                JOIN users u ON u.id = r.user_id
                WHERE r.event_id = ?';
        $params = [$eventId];
        if ($status !== null) {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY r.registered_at ASC';

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function lock(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM registrations WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        $registration = $statement->fetch();

        return $registration === false ? null : $registration;
    }

    public function create(int $eventId, int $userId, int $ticketTypeId, string $status, string $price): int
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO registrations (event_id, user_id, ticket_type_id, status, price, variable_symbol,
                                        payment_claimed_at, registered_at, decided_by, decided_at, cancelled_at)
             VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, NULL, NULL, NULL)'
        );
        $statement->execute([$eventId, $userId, $ticketTypeId, $status, $price, now_utc()]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function reuse(
        int $id,
        int $ticketTypeId,
        string $status,
        string $price
    ): void {
        $statement = $this->database->pdo()->prepare(
            'UPDATE registrations
             SET ticket_type_id = ?, status = ?, price = ?, variable_symbol = NULL,
                 payment_claimed_at = NULL, registered_at = ?, decided_by = NULL,
                 decided_at = NULL, cancelled_at = NULL
             WHERE id = ?'
        );
        $statement->execute([$ticketTypeId, $status, $price, now_utc(), $id]);
    }

    public function setVariableSymbol(int $id, string $variableSymbol): void
    {
        $statement = $this->database->pdo()->prepare('UPDATE registrations SET variable_symbol = ? WHERE id = ?');
        $statement->execute([$variableSymbol, $id]);
    }

    public function claimPayment(int $id): void
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE registrations SET payment_claimed_at = ? WHERE id = ?'
        );
        $statement->execute([now_utc(), $id]);
    }

    public function confirm(int $id, int $decidedBy): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE registrations
             SET status = 'confirmed', decided_by = ?, decided_at = ?, cancelled_at = NULL
             WHERE id = ?"
        );
        $statement->execute([$decidedBy, $now, $id]);
    }

    public function reject(int $id, int $decidedBy): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE registrations
             SET status = 'cancelled', decided_by = ?, decided_at = ?, cancelled_at = ?
             WHERE id = ?"
        );
        $statement->execute([$decidedBy, $now, $now, $id]);
    }

    public function cancel(int $id): void
    {
        $statement = $this->database->pdo()->prepare(
            "UPDATE registrations
             SET status = 'cancelled', cancelled_at = ?, decided_by = NULL, decided_at = NULL
             WHERE id = ?"
        );
        $statement->execute([now_utc(), $id]);
    }
}
