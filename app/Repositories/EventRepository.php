<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class EventRepository
{
    public function __construct(private Database $database)
    {
    }

    public function search(array $filters, int $page, int $perPage = 12): array
    {
        $where = ["e.status = 'approved'"];
        $params = [];

        if (empty($filters['archive'])) {
            $where[] = 'e.starts_at >= ?';
            $params[] = now_utc();
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . $this->escapeLike((string) $filters['q']) . '%';
            $where[] = '(e.title LIKE ? OR e.description LIKE ? OR v.name LIKE ? OR v.city LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['category_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $filters['category_ids'])));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = 'e.category_id IN (' . $placeholders . ')';
            foreach ($ids as $id) {
                $params[] = $id;
            }
        }
        if (($filters['city'] ?? '') !== '') {
            $where[] = 'v.city = ?';
            $params[] = $filters['city'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'e.starts_at >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'e.starts_at <= ?';
            $params[] = $filters['to'];
        }
        if (($filters['price'] ?? '') === 'free') {
            $where[] = "NOT EXISTS (SELECT 1 FROM ticket_types tt WHERE tt.event_id = e.id AND tt.price > 0)";
        } elseif (($filters['price'] ?? '') === 'paid') {
            $where[] = "EXISTS (SELECT 1 FROM ticket_types tt WHERE tt.event_id = e.id AND tt.price > 0)";
        }
        $fullSql = "(e.capacity IS NOT NULL AND (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending', 'confirmed')) >= e.capacity)";
        if (($filters['availability'] ?? '') === 'available') {
            $where[] = 'NOT ' . $fullSql;
        } elseif (($filters['availability'] ?? '') === 'full') {
            $where[] = $fullSql;
        }

        $whereSql = implode(' AND ', $where);
        $joinSql = ' FROM events e JOIN venues v ON v.id = e.venue_id JOIN categories c ON c.id = e.category_id';

        $countStatement = $this->database->pdo()->prepare('SELECT COUNT(*)' . $joinSql . ' WHERE ' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $order = match ($filters['sort'] ?? 'date') {
            'price' => '(SELECT COALESCE(MIN(tt.price), 0) FROM ticket_types tt WHERE tt.event_id = e.id) ASC, e.starts_at ASC',
            'title' => 'e.title ASC, e.starts_at ASC',
            default => 'e.starts_at ASC, e.id ASC',
        };

        $sql = 'SELECT e.*, v.name AS venue_name, v.city AS venue_city, c.name AS category_name,
                    (SELECT MIN(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id) AS min_price,
                    (SELECT MAX(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id) AS max_price,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN (\'pending\', \'confirmed\')) AS active_registrations,
                    (SELECT AVG(r.value) FROM ratings r WHERE r.event_id = e.id) AS rating_avg,
                    (SELECT COUNT(*) FROM ratings r WHERE r.event_id = e.id) AS rating_count'
            . $joinSql . ' WHERE ' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?';

        $statement = $this->database->pdo()->prepare($sql);
        $position = 1;
        foreach ($params as $param) {
            $statement->bindValue($position++, $param);
        }
        $page = max(1, $page);
        $statement->bindValue($position++, $perPage, PDO::PARAM_INT);
        $statement->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    public function cities(): array
    {
        $statement = $this->database->pdo()->query(
            "SELECT DISTINCT v.city
             FROM events e
             JOIN venues v ON v.id = e.venue_id
             WHERE e.status = 'approved'
             ORDER BY v.city ASC"
        );

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function calendarForUser(int $userId, string $fromUtc, string $toUtc): array
    {
        return $this->userCalendar($userId, $fromUtc, $toUtc, false);
    }

    public function exportForUser(int $userId): array
    {
        return $this->userCalendar($userId, null, null, true);
    }

    private function userCalendar(int $userId, ?string $fromUtc, ?string $toUtc, bool $activeOnly): array
    {
        $sql = "SELECT e.*, v.name AS venue_name, v.city AS venue_city,
                       v.street AS venue_street, v.postal_code AS venue_postal_code,
                       c.name AS category_name, r.status AS registration_status
                FROM events e
                JOIN venues v ON v.id = e.venue_id
                JOIN categories c ON c.id = e.category_id
                LEFT JOIN registrations r
                    ON r.event_id = e.id AND r.user_id = ? AND r.status = 'confirmed'
                WHERE e.status = 'approved' AND (e.manager_id = ? OR r.id IS NOT NULL)";
        $params = [$userId, $userId];

        if ($fromUtc !== null && $toUtc !== null) {
            $sql .= ' AND e.starts_at >= ? AND e.starts_at <= ?';
            $params[] = $fromUtc;
            $params[] = $toUtc;
        }
        if ($activeOnly) {
            $sql .= ' AND e.ends_at >= ?';
            $params[] = now_utc();
        }

        $sql .= ' ORDER BY e.starts_at ASC';
        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
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
                    v.postal_code AS venue_postal_code, c.name AS category_name,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('pending', 'confirmed')) AS active_registrations,
                    (SELECT AVG(r.value) FROM ratings r WHERE r.event_id = e.id) AS rating_avg,
                    (SELECT COUNT(*) FROM ratings r WHERE r.event_id = e.id) AS rating_count
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
