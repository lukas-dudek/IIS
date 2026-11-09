<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

final class UserRepository
{
    public function __construct(private Database $database)
    {
    }

    public function findActiveById(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, username, email, name, role, is_active, auth_version, created_at, last_login_at FROM users WHERE id = ? AND is_active = 1'
        );
        $statement->execute([$id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $statement->execute([$id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findByLogin(string $login): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $statement->execute([$login, mb_strtolower($login)]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = ?';
        $params = [$username];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = ?';
        $params = [mb_strtolower($email)];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $statement = $this->database->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $username, string $email, string $passwordHash, string $name): int
    {
        $now = now_utc();
        $statement = $this->database->pdo()->prepare(
            "INSERT INTO users (username, email, password_hash, name, role, is_active, auth_version, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'user', 1, 1, ?, ?)"
        );
        $statement->execute([$username, mb_strtolower($email), $passwordHash, $name, $now, $now]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE users SET name = ?, email = ?, updated_at = ? WHERE id = ?'
        );
        $statement->execute([$name, mb_strtolower($email), now_utc(), $id]);
    }

    public function updatePassword(int $id, string $passwordHash): int
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE users SET password_hash = ?, auth_version = auth_version + 1, updated_at = ? WHERE id = ?'
        );
        $statement->execute([$passwordHash, now_utc(), $id]);

        $statement = $this->database->pdo()->prepare('SELECT auth_version FROM users WHERE id = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }

    public function touchLastLogin(int $id): void
    {
        $statement = $this->database->pdo()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
        $statement->execute([now_utc(), $id]);
    }

    public function recordLoginAttempt(string $username, string $ip, bool $successful): void
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO login_attempts (username, ip, successful, attempted_at) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$username, $ip, $successful ? 1 : 0, now_utc()]);
    }

    public function countFailedAttempts(string $username, string $ip, string $sinceUtc): int
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND successful = 0 AND attempted_at >= ?'
        );
        $statement->execute([$username, $ip, $sinceUtc]);

        return (int) $statement->fetchColumn();
    }

    public function deleteOldAttempts(int $days): void
    {
        $threshold = (new DateTimeImmutable('-' . $days . ' days', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');
        $statement = $this->database->pdo()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $statement->execute([$threshold]);
    }
}
