<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\UserRepository;
use PDOException;
use RuntimeException;

final class UserService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function updateProfile(int $id, string $name, string $email): array
    {
        if ($this->users->emailExists($email, $id)) {
            throw new ValidationException(['email' => 'E-mail je již použitý.']);
        }

        try {
            $this->users->updateProfile($id, $name, $email);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000'
                && str_contains($exception->getMessage(), 'uq_users_email')) {
                throw new ValidationException(['email' => 'E-mail je již použitý.']);
            }
            throw $exception;
        }

        $user = $this->users->findById($id);
        if ($user === null) {
            throw new RuntimeException('Uživatele se nepodařilo načíst.');
        }

        unset($user['password_hash']);

        return $user;
    }

    public function changePassword(int $id, string $currentPassword, string $newPassword): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new RuntimeException('Uživatel neexistuje.');
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new ValidationException(['current_password' => 'Současné heslo není správné.']);
        }

        if (str_contains($newPassword, "\0")) {
            throw new ValidationException(['new_password' => 'Nové heslo obsahuje neplatný znak.']);
        }

        $this->users->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        $updated = $this->users->findById($id);
        if ($updated === null) {
            throw new RuntimeException('Uživatele se nepodařilo načíst.');
        }

        unset($updated['password_hash']);

        return $updated;
    }
}
