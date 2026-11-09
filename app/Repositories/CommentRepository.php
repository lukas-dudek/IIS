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

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT c.*, e.slug AS event_slug, e.title AS event_title, u.name AS author_name
             FROM comments c
             JOIN events e ON e.id = c.event_id
             JOIN users u ON u.id = c.user_id
             WHERE c.id = ?'
        );
        $statement->execute([$id]);
        $comment = $statement->fetch();

        return $comment === false ? null : $comment;
    }

    public function findForEventUser(int $eventId, int $userId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM comments WHERE event_id = ? AND user_id = ?'
        );
        $statement->execute([$eventId, $userId]);
        $comment = $statement->fetch();

        return $comment === false ? null : $comment;
    }

    public function create(int $eventId, int $userId, string $body): int
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "INSERT INTO comments (event_id, user_id, body, status, created_at, updated_at)
             VALUES (?, ?, ?, 'visible', ?, ?)"
        );
        $statement->execute([$eventId, $userId, $body, $now, $now]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function update(int $id, string $body): void
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE comments SET body = ?, updated_at = ? WHERE id = ?'
        );
        $statement->execute([$body, now_utc(), $id]);
    }

    public function delete(int $id): void
    {
        $statement = $this->database->pdo()->prepare('DELETE FROM comments WHERE id = ?');
        $statement->execute([$id]);
    }

    public function forModeration(?string $status, ?int $eventId): array
    {
        $sql = 'SELECT c.*, e.title AS event_title, u.name AS author_name, m.name AS moderator_name
                FROM comments c
                JOIN events e ON e.id = c.event_id
                JOIN users u ON u.id = c.user_id
                LEFT JOIN users m ON m.id = c.moderated_by
                WHERE 1 = 1';
        $params = [];
        if ($status !== null) {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        if ($eventId !== null) {
            $sql .= ' AND c.event_id = ?';
            $params[] = $eventId;
        }
        $sql .= ' ORDER BY c.created_at DESC';

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function moderationEvents(): array
    {
        $statement = $this->database->pdo()->query(
            'SELECT DISTINCT e.id, e.title
             FROM comments c
             JOIN events e ON e.id = c.event_id
             ORDER BY e.title ASC'
        );

        return $statement->fetchAll();
    }

    public function hide(int $id, int $moderatorId, string $reason): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE comments
             SET status = 'hidden', moderated_by = ?, moderated_at = ?, hidden_reason = ?, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$moderatorId, $now, $reason, $now, $id]);
    }

    public function show(int $id, int $moderatorId): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE comments
             SET status = 'visible', moderated_by = ?, moderated_at = ?, hidden_reason = NULL, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$moderatorId, $now, $now, $id]);
    }
}
