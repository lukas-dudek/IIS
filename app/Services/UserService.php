<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\HttpException;
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

    public function get(int $id): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new HttpException(404, 'Uživatel nenalezen.');
        }

        unset($user['password_hash']);

        return $user;
    }

    public function searchForAdmin(string $query, string $role, ?bool $active): array
    {
        return $this->users->searchForAdmin($query, $role, $active);
    }

    public function activeAdminCount(): int
    {
        return $this->users->countActiveAdmins();
    }

    public function createUser(array $data): array
    {
        if (!in_array($data['role'], ['admin', 'moderator', 'user'], true)) {
            throw new ValidationException(['role' => 'Vyberte platnou roli.']);
        }
        if (str_contains($data['password'], "\0")) {
            throw new ValidationException(['password' => 'Heslo obsahuje neplatný znak.']);
        }

        $errors = [];
        if ($this->users->usernameExists($data['username'])) {
            $errors['username'] = 'Uživatelské jméno je již obsazené.';
        }
        if ($this->users->emailExists($data['email'])) {
            $errors['email'] = 'E-mail je již použitý.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $id = $this->users->create(
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['name'],
                $data['role']
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            if (str_contains($exception->getMessage(), 'uq_users_username')) {
                throw new ValidationException(['username' => 'Uživatelské jméno je již obsazené.']);
            }
            if (str_contains($exception->getMessage(), 'uq_users_email')) {
                throw new ValidationException(['email' => 'E-mail je již použitý.']);
            }
            throw $exception;
        }

        return $this->get($id);
    }

    public function updateUser(int $id, string $name, string $email, string $role): array
    {
        if (!in_array($role, ['admin', 'moderator', 'user'], true)) {
            throw new ValidationException(['role' => 'Vyberte platnou roli.']);
        }

        $target = $this->get($id);
        if ($this->users->emailExists($email, $id)) {
            throw new ValidationException(['email' => 'E-mail je již použitý.']);
        }
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1
            && $role !== 'admin' && $this->users->countActiveAdmins() <= 1) {
            throw new BusinessRuleException('Poslednímu aktivnímu administrátorovi nelze odebrat roli.');
        }

        $bump = $target['role'] !== $role;
        try {
            $this->users->updateUser($id, $name, $email, $role, $bump);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000'
                && str_contains($exception->getMessage(), 'uq_users_email')) {
                throw new ValidationException(['email' => 'E-mail je již použitý.']);
            }
            throw $exception;
        }

        return $this->get($id);
    }

    public function resetPassword(int $id, string $newPassword): array
    {
        $this->get($id);
        if (str_contains($newPassword, "\0")) {
            throw new ValidationException(['new_password' => 'Nové heslo obsahuje neplatný znak.']);
        }

        $this->users->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        return $this->get($id);
    }

    public function deactivate(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new BusinessRuleException('Nemůžete deaktivovat sám sebe.');
        }

        $target = $this->get($id);
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1
            && $this->users->countActiveAdmins() <= 1) {
            throw new BusinessRuleException('Posledního aktivního administrátora nelze deaktivovat.');
        }

        $this->users->setActive($id, false);
    }

    public function activate(int $id): void
    {
        $this->get($id);
        $this->users->setActive($id, true);
    }

    public function delete(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new BusinessRuleException('Nemůžete smazat sám sebe.');
        }

        $target = $this->get($id);
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1
            && $this->users->countActiveAdmins() <= 1) {
            throw new BusinessRuleException('Posledního aktivního administrátora nelze smazat.');
        }
        if ($this->users->countReferences($id) > 0) {
            throw new BusinessRuleException('Uživatele nelze smazat, protože má navázané záznamy. Můžete ho deaktivovat.');
        }

        try {
            $this->users->delete($id);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new BusinessRuleException('Uživatele nelze smazat, protože má navázané záznamy. Můžete ho deaktivovat.');
            }
            throw $exception;
        }
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
