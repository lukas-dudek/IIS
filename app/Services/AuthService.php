<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function register(array $data): User
    {
        $errors = [];
        if (User::query()->where('username', $data['username'])->exists()) {
            $errors['username'] = 'Uživatelské jméno je již obsazené.';
        }
        if (User::query()->where('email', $data['email'])->exists()) {
            $errors['email'] = 'E-mail je již použitý.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(fn (): User => User::query()->create([
            'username' => $data['username'],
            'email' => $data['email'],
            'name' => $data['name'],
            'password_hash' => Hash::make($data['password']),
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]));
    }

    /**
     * @return array{user: ?User, error: ?string}
     */
    public function attempt(string $login, string $password, string $ip): array
    {
        $login = trim($login);
        $key = mb_substr($login, 0, 50);
        $since = utc_now()->subMinutes(self::WINDOW_MINUTES);

        LoginAttempt::query()->where('attempted_at', '<', utc_now()->subDay())->delete();

        $failures = LoginAttempt::query()
            ->where('successful', false)
            ->where('attempted_at', '>=', $since)
            ->where(fn ($query) => $query->where('login', $key)->orWhere('ip', $ip))
            ->count();

        if ($failures >= self::MAX_ATTEMPTS) {
            $this->record($key, $ip, false);

            return ['user' => null, 'error' => 'Příliš mnoho neúspěšných pokusů. Zkuste to za 15 minut.'];
        }

        $user = User::query()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->first();

        $hash = $user?->password_hash ?? '$2y$10$JPWXthy5L96tA3cbv0q2SuFKm0JWp0cmv3oa2ql9mehKJUj9bPg6m';

        if (!Hash::check($password, $hash) || $user === null || !$user->isActive()) {
            $this->record($key, $ip, false);

            return ['user' => null, 'error' => 'Neplatné přihlašovací údaje.'];
        }

        $this->record($key, $ip, true);
        $user->forceFill(['last_login_at' => utc_now()])->save();

        return ['user' => $user, 'error' => null];
    }

    private function record(string $login, string $ip, bool $successful): void
    {
        LoginAttempt::query()->create([
            'login' => $login,
            'ip' => $ip,
            'successful' => $successful,
            'attempted_at' => utc_now(),
        ]);
    }
}
