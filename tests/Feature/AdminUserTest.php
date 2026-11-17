<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCreatesUser(): void
    {
        $user = $this->service()->create([
            'username' => 'novyclovek',
            'name' => 'Nový Člověk',
            'email' => 'novy@example.test',
            'role' => User::ROLE_MODERATOR,
            'password' => 'heslo1234',
        ]);

        $this->assertSame(User::ROLE_MODERATOR, $user->role);
        $this->assertTrue($user->isActive());
        $this->assertNotSame('heslo1234', $user->password_hash);
    }

    public function testAdminCannotReuseUsernameOrEmail(): void
    {
        $admin = $this->admin();
        $existing = $this->user('existujici');

        $this->expectException(ValidationException::class);

        $this->service()->create([
            'username' => $existing->username,
            'name' => 'Kdokoli',
            'email' => 'jiny@example.test',
            'role' => User::ROLE_USER,
            'password' => 'heslo1234',
        ]);
    }

    public function testAdminCannotDeactivateSelf(): void
    {
        $admin = $this->admin();

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('Nemůžete deaktivovat sám sebe.');

        $this->service()->deactivate($admin, $admin);
    }

    public function testLastActiveAdminCannotBeDeactivatedByOtherAdmin(): void
    {
        $first = $this->admin();
        $second = $this->user('druhyadmin', User::ROLE_ADMIN);

        $this->service()->deactivate($first, $second);

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('Posledního aktivního administrátora nelze deaktivovat.');

        $this->service()->deactivate($second, $first->fresh());
    }

    public function testLastActiveAdminCannotLoseRole(): void
    {
        $admin = $this->admin();

        $this->expectException(DomainRuleException::class);

        $this->service()->update($admin, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_USER,
        ]);
    }

    public function testRoleChangeIncrementsAuthVersion(): void
    {
        $admin = $this->admin();
        $other = $this->user('zmenarole');
        $version = $other->auth_version;

        $this->service()->update($other, [
            'name' => $other->name,
            'email' => $other->email,
            'role' => User::ROLE_MODERATOR,
        ]);

        $this->assertGreaterThan($version, $other->fresh()->auth_version);
    }

    public function testPasswordResetIncrementsAuthVersion(): void
    {
        $admin = $this->admin();
        $user = $this->user('reset');
        $version = $user->auth_version;

        $this->service()->resetPassword($user, 'noveheslo1');

        $this->assertGreaterThan($version, $user->fresh()->auth_version);
    }

    public function testUserWithReferencesCannotBeDeleted(): void
    {
        $admin = $this->admin();
        $manager = $this->user('manazer');
        $this->event($manager);

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('Uživatele nelze smazat, protože má navázané záznamy. Můžete ho deaktivovat.');

        $this->service()->delete($manager, $admin);
    }

    public function testUserWithoutReferencesCanBeDeleted(): void
    {
        $admin = $this->admin();
        $user = $this->user('bezreference');

        $this->service()->delete($user, $admin);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function testAdminCannotDeleteSelf(): void
    {
        $admin = $this->admin();

        $this->expectException(DomainRuleException::class);

        $this->service()->delete($admin, $admin);
    }

    public function testModeratorCannotOpenUserAdministration(): void
    {
        $moderator = $this->moderator();

        $this->actingAs($moderator)->get('/admin/uzivatele')->assertForbidden();
        $this->actingAs($moderator)->post('/admin/uzivatele/novy', [
            'username' => 'x',
            'name' => 'X',
            'email' => 'x@example.test',
            'role' => User::ROLE_USER,
            'password' => 'heslo1234',
            'password_confirmation' => 'heslo1234',
        ])->assertForbidden();
    }

    public function testAdminUserFormKeepsValuesOnError(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->from('/admin/uzivatele/novy')
            ->post('/admin/uzivatele/novy', [
                'username' => 'platne-jmeno',
                'name' => 'Jméno',
                'email' => 'neplatny-email',
                'role' => User::ROLE_USER,
                'password' => 'heslo1234',
                'password_confirmation' => 'heslo1234',
            ]);

        $response->assertRedirect('/admin/uzivatele/novy');
        $response->assertSessionHasErrors('email');
        $response->assertSessionHasInput('username', 'platne-jmeno');
    }

    public function testAdminCreatesUserThroughHttp(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/uzivatele/novy', [
            'username' => 'httpuzivatel',
            'name' => 'HTTP Uživatel',
            'email' => 'http@example.test',
            'role' => User::ROLE_USER,
            'password' => 'heslo1234',
            'password_confirmation' => 'heslo1234',
        ])->assertRedirect('/admin/uzivatele');

        $this->assertDatabaseHas('users', ['username' => 'httpuzivatel']);
    }

    private function service(): UserService
    {
        return $this->app->make(UserService::class);
    }
}
