<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private UserRepository $users)
    {
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = Session::get('user_id');
        $version = Session::get('auth_version');
        if (!is_numeric($id) || !is_numeric($version)) {
            return null;
        }
        $id = (int) $id;
        $version = (int) $version;

        $user = $this->users->findActiveById($id);
        if ($user === null || (int) $user['auth_version'] !== $version) {
            $this->logout();
            return null;
        }

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function role(): ?string
    {
        $user = $this->user();

        return $user === null ? null : (string) $user['role'];
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $user['role'] === 'admin' || $user['role'] === $role;
    }

    public function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('auth_version', (int) $user['auth_version']);
        Session::set('_last_activity', time());
        Csrf::rotate();

        $this->user = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        Session::remove('user_id');
        Session::remove('auth_version');
        Session::remove('_last_activity');
        Csrf::rotate();

        $this->user = null;
        $this->loaded = true;
    }
}
