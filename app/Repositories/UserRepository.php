<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

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
}
