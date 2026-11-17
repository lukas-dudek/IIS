<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserService
{
    /**
     * @return array<string, string>
     */
    public function create(array $data): User
    {
        $errors = [];
        if (User::query()->where('username', $data['username'])->exists()) {
            $errors['username'] = 'Uživatelské jméno je již obsazené.';
        }
        if (User::query()->where('email', $data['email'])->exists()) {
            $errors['email'] = 'E-mail je již použitý.';
        }
        if (!in_array($data['role'], User::ROLES, true)) {
            $errors['role'] = 'Vyberte platnou roli.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return User::query()->create([
            'username' => $data['username'],
            'email' => $data['email'],
            'name' => $data['name'],
            'password_hash' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);
    }

    public function update(User $user, array $data): User
    {
        $errors = [];
        if (User::query()->where('email', $data['email'])->whereKeyNot($user->id)->exists()) {
            $errors['email'] = 'E-mail je již použitý.';
        }
        if (!in_array($data['role'], User::ROLES, true)) {
            $errors['role'] = 'Vyberte platnou roli.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $roleChanged = $user->role !== $data['role'];
        if ($roleChanged && $user->role === User::ROLE_ADMIN && $user->isActive()
            && $this->activeAdminCount() <= 1) {
            throw new DomainRuleException('Poslednímu aktivnímu administrátorovi nelze odebrat roli.');
        }

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        if ($roleChanged) {
            $user->auth_version++;
        }

        $user->save();

        return $user;
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->forceFill([
            'password_hash' => Hash::make($newPassword),
            'auth_version' => $user->auth_version + 1,
        ])->save();
    }

    public function deactivate(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new DomainRuleException('Nemůžete deaktivovat sám sebe.');
        }
        if ($user->role === User::ROLE_ADMIN && $user->isActive() && $this->activeAdminCount() <= 1) {
            throw new DomainRuleException('Posledního aktivního administrátora nelze deaktivovat.');
        }

        $user->forceFill(['is_active' => false, 'auth_version' => $user->auth_version + 1])->save();
    }

    public function activate(User $user): void
    {
        $user->forceFill(['is_active' => true])->save();
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new DomainRuleException('Nemůžete smazat sám sebe.');
        }
        if ($user->role === User::ROLE_ADMIN && $user->isActive() && $this->activeAdminCount() <= 1) {
            throw new DomainRuleException('Posledního aktivního administrátora nelze smazat.');
        }
        if ($this->referenceCount($user) > 0) {
            throw new DomainRuleException('Uživatele nelze smazat, protože má navázané záznamy. Můžete ho deaktivovat.');
        }

        try {
            $user->delete();
        } catch (\Illuminate\Database\QueryException) {
            throw new DomainRuleException('Uživatele nelze smazat, protože má navázané záznamy. Můžete ho deaktivovat.');
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password_hash)) {
            throw ValidationException::withMessages(['current_password' => 'Současné heslo není správné.']);
        }

        $user->forceFill([
            'password_hash' => Hash::make($newPassword),
            'auth_version' => $user->auth_version + 1,
        ])->save();
    }

    public function updateProfile(User $user, string $name, string $email): User
    {
        if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'E-mail je již použitý.']);
        }

        $user->forceFill(['name' => $name, 'email' => $email])->save();

        return $user;
    }

    public function activeAdminCount(): int
    {
        return User::query()->admins()->active()->count();
    }

    public function referenceCount(User $user): int
    {
        $counts = [
            DB::table('events')->where('manager_id', $user->id)->count(),
            DB::table('events')->where('reviewed_by', $user->id)->count(),
            DB::table('venues')->where('proposed_by', $user->id)->count(),
            DB::table('venues')->where('reviewed_by', $user->id)->count(),
            DB::table('categories')->where('proposed_by', $user->id)->count(),
            DB::table('categories')->where('reviewed_by', $user->id)->count(),
            DB::table('registrations')->where('user_id', $user->id)->count(),
            DB::table('comments')->where('user_id', $user->id)->count(),
            DB::table('ratings')->where('user_id', $user->id)->count(),
        ];

        return array_sum($counts);
    }
}
