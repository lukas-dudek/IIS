<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class EventRepository
{
    public function __construct(private Database $database)
    {
    }

    public function mine(int $managerId): array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT e.*, v.name AS venue_name, v.city AS venue_city,
                    c.name AS category_name,
                    (SELECT COUNT(*) FROM registrations r
                     WHERE r.event_id = e.id AND r.status IN ('pending', 'confirmed')) AS active_registrations
             FROM events e
             JOIN venues v ON v.id = e.venue_id
             JOIN categories c ON c.id = e.category_id
             WHERE e.manager_id = ?
             ORDER BY e.starts_at DESC"
        );
        $statement->execute([$managerId]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT e.*, v.name AS venue_name, v.city AS venue_city,
                    c.name AS category_name, m.name AS manager_name, m.username AS manager_username
             FROM events e
             JOIN venues v ON v.id = e.venue_id
             JOIN categories c ON c.id = e.category_id
             JOIN users m ON m.id = e.manager_id
             WHERE e.id = ?'
        );
        $statement->execute([$id]);
        $event = $statement->fetch();

        return $event === false ? null : $event;
    }

    public function findForManager(int $id, int $managerId, bool $isAdmin): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT e.*, v.name AS venue_name, v.city AS venue_city,
                    c.name AS category_name
             FROM events e
             JOIN venues v ON v.id = e.venue_id
             JOIN categories c ON c.id = e.category_id
             WHERE e.id = ? AND (e.manager_id = ? OR ? = 1)'
        );
        $statement->execute([$id, $managerId, $isAdmin ? 1 : 0]);
        $event = $statement->fetch();

        return $event === false ? null : $event;
    }

    public function lock(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        $event = $statement->fetch();

        return $event === false ? null : $event;
    }

    public function findBySlug(string $slug): ?array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT e.*, v.name AS venue_name, v.city AS venue_city, v.street AS venue_street,
                    v.postal_code AS venue_postal_code, c.name AS category_name
             FROM events e
             JOIN venues v ON v.id = e.venue_id
             JOIN categories c ON c.id = e.category_id
             WHERE e.slug = ? AND e.status = 'approved'"
        );
        $statement->execute([$slug]);
        $event = $statement->fetch();

        return $event === false ? null : $event;
    }

    public function pendingForModeration(): array
    {
        $statement = $this->database->pdo()->query(
            "SELECT e.*, v.name AS venue_name, v.city AS venue_city,
                    c.name AS category_name, m.name AS manager_name
             FROM events e
             JOIN venues v ON v.id = e.venue_id
             JOIN categories c ON c.id = e.category_id
             JOIN users m ON m.id = e.manager_id
             WHERE e.status = 'pending'
             ORDER BY e.submitted_at ASC"
        );

        return $statement->fetchAll();
    }

    public function findForModeration(int $id): ?array
    {
        return $this->find($id);
    }

    public function activeRegistrationCount(int $eventId): int
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('pending', 'confirmed')"
        );
        $statement->execute([$eventId]);

        return (int) $statement->fetchColumn();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM events WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    public function create(array $data, string $slug, string $status, ?string $submittedAt): int
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO events (slug, title, description, starts_at, ends_at, venue_id, category_id,
                                 capacity, cover_path, payment_note, status, manager_id, submitted_at,
                                 created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $slug,
            $data['title'],
            $data['description'],
            $data['starts_at'],
            $data['ends_at'],
            $data['venue_id'],
            $data['category_id'],
            $data['capacity'],
            $data['payment_note'],
            $status,
            $data['manager_id'],
            $submittedAt,
            $now,
            $now,
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function updateFields(
        int $id,
        array $data,
        string $slug,
        string $status,
        ?string $submittedAt,
        bool $clearReview = false
    ): void {
        $sql = 'UPDATE events
             SET slug = ?, title = ?, description = ?, starts_at = ?, ends_at = ?, venue_id = ?,
                 category_id = ?, capacity = ?, payment_note = ?, status = ?, submitted_at = ?,
                 updated_at = ?';
        if ($clearReview) {
            $sql .= ', reviewed_by = NULL, reviewed_at = NULL, rejection_reason = NULL';
        }
        $sql .= ' WHERE id = ?';

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute([
            $slug,
            $data['title'],
            $data['description'],
            $data['starts_at'],
            $data['ends_at'],
            $data['venue_id'],
            $data['category_id'],
            $data['capacity'],
            $data['payment_note'],
            $status,
            $submittedAt,
            now_utc(),
            $id,
        ]);
    }

    public function submit(int $id): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE events
             SET status = 'pending', submitted_at = ?, reviewed_by = NULL, reviewed_at = NULL,
                 rejection_reason = NULL, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$now, $now, $id]);
    }

    public function approve(int $id, int $reviewerId): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE events
             SET status = 'approved', reviewed_by = ?, reviewed_at = ?, rejection_reason = NULL,
                 updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reviewerId, $now, $now, $id]);
    }

    public function reject(int $id, int $reviewerId, string $reason): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE events
             SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, rejection_reason = ?,
                 updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reviewerId, $now, $reason, $now, $id]);
    }

    public function cancel(int $id, string $reason): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE events
             SET status = 'cancelled', cancelled_reason = ?, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reason, $now, $id]);
    }

    public function cancelActiveRegistrations(int $eventId): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE registrations
             SET status = 'cancelled', cancelled_at = ?
             WHERE event_id = ? AND status IN ('pending', 'confirmed')"
        );
        $statement->execute([$now, $eventId]);
    }
}
