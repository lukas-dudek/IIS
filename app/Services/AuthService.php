<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

final class AuthService
{
    private const DUMMY_HASH = '$2y$10$JPWXthy5L96tA3cbv0q2SuFKm0JWp0cmv3oa2ql9mehKJUj9bPg6m';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function __construct(private UserRepository $users)
    {
    }

    public function register(array $data): array
    {
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
                $data['name']
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

        $user = $this->users->findById($id);
        if ($user === null) {
            throw new \RuntimeException('Nového uživatele se nepodařilo načíst.');
        }

        unset($user['password_hash']);

        return $user;
    }

    public function login(string $login, string $password, string $ip): array
    {
        $login = trim($login);
        $attemptKey = mb_substr($login, 0, 50);
        $this->users->deleteOldAttempts(30);

        $since = (new DateTimeImmutable('-' . self::WINDOW_MINUTES . ' minutes', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');
        if ($this->users->countFailedAttempts($attemptKey, $ip, $since) >= self::MAX_ATTEMPTS) {
            $this->users->recordLoginAttempt($attemptKey, $ip, false);

            return [
                'user' => null,
                'error' => 'Příliš mnoho neúspěšných pokusů. Zkuste to za 15 minut.',
            ];
        }

        $user = $this->users->findByLogin($login);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid || (int) $user['is_active'] !== 1) {
            $this->users->recordLoginAttempt($attemptKey, $ip, false);

            return [
                'user' => null,
                'error' => 'Neplatné přihlašovací údaje.',
            ];
        }

        $this->users->recordLoginAttempt($attemptKey, $ip, true);
        $this->users->touchLastLogin((int) $user['id']);
        unset($user['password_hash']);

        return ['user' => $user, 'error' => null];
    }
}
