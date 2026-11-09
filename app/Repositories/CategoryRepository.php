<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class CategoryRepository
{
    public function __construct(private Database $database)
    {
    }

    public function approved(): array
    {
        $statement = $this->database->pdo()->query(
            "SELECT * FROM categories WHERE status = 'approved' ORDER BY name ASC"
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT c.*, p.name AS parent_name,
                    pu.username AS proposer_username, pu.name AS proposer_name,
                    ru.name AS reviewer_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             JOIN users pu ON pu.id = c.proposed_by
             LEFT JOIN users ru ON ru.id = c.reviewed_by
             WHERE c.id = ?'
        );
        $statement->execute([$id]);
        $category = $statement->fetch();

        return $category === false ? null : $category;
    }

    public function findApproved(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            "SELECT * FROM categories WHERE id = ? AND status = 'approved'"
        );
        $statement->execute([$id]);
        $category = $statement->fetch();

        return $category === false ? null : $category;
    }

    public function findOwned(int $id, int $userId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT c.*, p.name AS parent_name,
                    ru.name AS reviewer_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             LEFT JOIN users ru ON ru.id = c.reviewed_by
             WHERE c.id = ? AND c.proposed_by = ?'
        );
        $statement->execute([$id, $userId]);
        $category = $statement->fetch();

        return $category === false ? null : $category;
    }

    public function allForModeration(): array
    {
        $statement = $this->database->pdo()->query(
            'SELECT c.*, p.name AS parent_name,
                    pu.username AS proposer_username, pu.name AS proposer_name,
                    ru.name AS reviewer_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             JOIN users pu ON pu.id = c.proposed_by
             LEFT JOIN users ru ON ru.id = c.reviewed_by
             ORDER BY c.name ASC'
        );

        return $statement->fetchAll();
    }

    public function proposalsForUser(int $userId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT c.*, p.name AS parent_name, ru.name AS reviewer_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             LEFT JOIN users ru ON ru.id = c.reviewed_by
             WHERE c.proposed_by = ?
             ORDER BY c.created_at DESC'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function childrenOf(int $id): array
    {
        $statement = $this->database->pdo()->prepare('SELECT id FROM categories WHERE parent_id = ?');
        $statement->execute([$id]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function descendantIds(int $id): array
    {
        $descendants = [];
        $frontier = [$id];

        while ($frontier !== []) {
            $placeholders = implode(',', array_fill(0, count($frontier), '?'));
            $statement = $this->database->pdo()->prepare(
                'SELECT id FROM categories WHERE parent_id IN (' . $placeholders . ')'
            );
            $statement->execute($frontier);
            $children = array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
            $children = array_values(array_diff($children, $descendants, [$id]));
            if ($children === []) {
                break;
            }
            $descendants = array_merge($descendants, $children);
            $frontier = $children;
        }

        return $descendants;
    }

    public function depthOf(int $id): int
    {
        $depth = 1;
        $current = $id;

        for ($guard = 0; $guard < 10; $guard++) {
            $statement = $this->database->pdo()->prepare('SELECT parent_id FROM categories WHERE id = ?');
            $statement->execute([$current]);
            $parent = $statement->fetchColumn();
            if ($parent === false || $parent === null) {
                break;
            }
            $depth++;
            $current = (int) $parent;
        }

        return $depth;
    }

    public function heightOf(int $id): int
    {
        $children = $this->childrenOf($id);
        if ($children === []) {
            return 1;
        }

        $height = 0;
        foreach ($children as $child) {
            $height = max($height, $this->heightOf($child));
        }

        return 1 + $height;
    }

    public function nameExists(string $name, ?int $parentId, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE name = ? AND parent_id <=> ?';
        $params = [$name, $parentId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    public function create(
        string $name,
        string $slug,
        ?int $parentId,
        ?string $description,
        string $status,
        int $proposedBy,
        ?int $reviewedBy,
        ?string $reviewedAt,
        ?string $rejectionReason
    ): int {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO categories (name, slug, parent_id, description, status, proposed_by,
                                     reviewed_by, reviewed_at, rejection_reason, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $name,
            $slug,
            $parentId,
            $description,
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

    public function update(
        int $id,
        string $name,
        string $slug,
        ?int $parentId,
        ?string $description,
        string $status,
        ?int $reviewedBy,
        ?string $reviewedAt,
        ?string $rejectionReason
    ): void {
        $statement = $this->database->pdo()->prepare(
            'UPDATE categories
             SET name = ?, slug = ?, parent_id = ?, description = ?, status = ?,
                 reviewed_by = ?, reviewed_at = ?, rejection_reason = ?, updated_at = ?
             WHERE id = ?'
        );
        $statement->execute([
            $name,
            $slug,
            $parentId,
            $description,
            $status,
            $reviewedBy,
            $reviewedAt,
            $rejectionReason,
            now_utc(),
            $id,
        ]);
    }

    public function approve(int $id, int $reviewerId): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE categories
             SET status = 'approved', reviewed_by = ?, reviewed_at = ?, rejection_reason = NULL, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reviewerId, $now, $now, $id]);
    }

    public function reject(int $id, int $reviewerId, string $reason): void
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "UPDATE categories
             SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, rejection_reason = ?, updated_at = ?
             WHERE id = ?"
        );
        $statement->execute([$reviewerId, $now, $reason, $now, $id]);
    }

    public function deleteIfUnused(int $id): bool
    {
        $statement = $this->database->pdo()->prepare(
            'DELETE FROM categories
             WHERE id = ?
               AND NOT EXISTS (SELECT 1 FROM categories WHERE parent_id = ?)
               AND NOT EXISTS (SELECT 1 FROM events WHERE category_id = ?)'
        );
        $statement->execute([$id, $id, $id]);

        return $statement->rowCount() > 0;
    }
}
