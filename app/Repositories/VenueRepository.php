<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class VenueRepository
{
    public function __construct(private Database $database)
    {
    }

    public function approved(): array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT v.*,
                (SELECT COUNT(*) FROM events e
                 WHERE e.venue_id = v.id AND e.status = 'approved' AND e.starts_at >= ?) AS upcoming_count
             FROM venues v
             WHERE v.status = 'approved'
             ORDER BY v.city ASC, v.name ASC"
        );
        $statement->execute([now_utc()]);

        return $statement->fetchAll();
    }

    public function findApproved(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT * FROM venues WHERE id = ? AND status = 'approved'"
        );
        $statement->execute([$id]);
        $venue = $statement->fetch();

        return $venue === false ? null : $venue;
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT v.*, pu.username AS proposer_username, pu.name AS proposer_name,
                    ru.name AS reviewer_name
             FROM venues v
             JOIN users pu ON pu.id = v.proposed_by
             LEFT JOIN users ru ON ru.id = v.reviewed_by
             WHERE v.id = ?'
        );
        $statement->execute([$id]);
        $venue = $statement->fetch();

        return $venue === false ? null : $venue;
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT v.*, pu.username AS proposer_username, pu.name AS proposer_name,
                    ru.name AS reviewer_name
             FROM venues v
             JOIN users pu ON pu.id = v.proposed_by
             LEFT JOIN users ru ON ru.id = v.reviewed_by
             WHERE v.id = ? AND v.proposed_by = ?'
        );
        $statement->execute([$id, $userId]);
        $venue = $statement->fetch();

        return $venue === false ? null : $venue;
    }

    public function allForModeration(): array
    {
        $statement = $this->database->pdo()->query(
            "SELECT v.*, pu.username AS proposer_username, pu.name AS proposer_name,
                    ru.name AS reviewer_name
             FROM venues v
             JOIN users pu ON pu.id = v.proposed_by
             LEFT JOIN users ru ON ru.id = v.reviewed_by
             ORDER BY CASE v.status
                 WHEN 'pending' THEN 0
                 WHEN 'rejected' THEN 1
                 ELSE 2
             END, v.city ASC, v.name ASC"
        );

        return $statement->fetchAll();
    }

    public function proposalsForUser(int $userId): array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT v.*, ru.name AS reviewer_name
             FROM venues v
             LEFT JOIN users ru ON ru.id = v.reviewed_by
             WHERE v.proposed_by = ?
             ORDER BY v.created_at DESC"
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function upcomingEvents(int $venueId): array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT e.id, e.slug, e.title, e.starts_at, e.ends_at, e.capacity, e.status,
                    c.name AS category_name
             FROM events e
             JOIN categories c ON c.id = e.category_id
             WHERE e.venue_id = ? AND e.status = 'approved' AND e.starts_at >= ?
             ORDER BY e.starts_at ASC"
        );
        $statement->execute([$venueId, now_utc()]);

        return $statement->fetchAll();
    }

    public function create(
        string $name,
        string $street,
        string $city,
        ?string $postalCode,
        ?string $description,
        ?string $photoPath,
        string $status,
        int $proposedBy,
        ?int $reviewedBy,
        ?string $reviewedAt,
        ?string $rejectionReason
    ): int {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO venues (name, street, city, postal_code, description, photo_path, status,
                                 proposed_by, reviewed_by, reviewed_at, rejection_reason, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $name,
            $street,
            $city,
            $postalCode,
            $description,
            $photoPath,
            $status,
            $proposedBy,
            $reviewedBy,
            $reviewedAt,
            $rejectionReason,
            $now,
            $now,
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function updateDetails(
        int $id,
        string $name,
        string $street,
        string $city,
        ?string $postalCode,
        ?string $description,
        ?string $photoPath
    ): void {
        $statement = $this->database->pdo()->prepare(
            'UPDATE venues
             SET name = ?, street = ?, city = ?, postal_code = ?, description = ?, photo_path = ?, updated_at = ?
             WHERE id = ?'
        );
        $statement->execute([$name, $street, $city, $postalCode, $description, $photoPath, now_utc(), $id]);
    }

    public function updateProposal(
        int $id,
        string $name,
        string $street,
        string $city,
        ?string $postalCode,
        ?string $description,
        ?string $photoPath
    ): void {
        $statement = $this->database->pdo()->prepare(
            "UPDATE venues
             SET name = ?, street = ?, city = ?, postal_code = ?, description = ?, photo_path = ?,
                 status = 'pending', reviewed_by = NULL, reviewed_at = NULL, rejection_reason = NULL, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$name, $street, $city, $postalCode, $description, $photoPath, now_utc(), $id]);
    }

    public function approve(int $id, int $reviewerId): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE venues
             SET status = 'approved', reviewed_by = ?, reviewed_at = ?, rejection_reason = NULL, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reviewerId, $now, $now, $id]);
    }

    public function reject(int $id, int $reviewerId, string $reason): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE venues
             SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, rejection_reason = ?, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reviewerId, $now, $reason, $now, $id]);
    }

    public function deleteIfUnused(int $id): bool
    {
        $statement = $this->database->pdo()->prepare(
            'DELETE FROM venues WHERE id = ? AND NOT EXISTS (SELECT 1 FROM events WHERE venue_id = ?)'
        );
        $statement->execute([$id, $id]);

        return $statement->rowCount() > 0;
    }
}
