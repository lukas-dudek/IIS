<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function testVisitorSeesPublicEventList(): void
    {
        $manager = $this->user('manager');
        $event = $this->event($manager);

        $response = $this->get('/udalosti');

        $response->assertOk();
        $response->assertSee($event->title);
    }

    public function testVisitorDoesNotSeeDraftEvent(): void
    {
        $manager = $this->user('manager');
        $draft = $this->event($manager, ['status' => Event::STATUS_DRAFT]);

        $this->get('/udalosti')->assertDontSee($draft->title);
        $this->get('/udalosti/' . $draft->slug)->assertNotFound();
    }

    public function testUserCanRegisterAndIsLoggedIn(): void
    {
        $response = $this->post('/registrace', [
            'username' => 'novy',
            'name' => 'Nový Uživatel',
            'email' => 'novy@example.test',
            'password' => 'heslo1234',
            'password_confirmation' => 'heslo1234',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'novy', 'role' => User::ROLE_USER]);
    }

    public function testRegistrationRejectsDuplicateUsername(): void
    {
        User::query()->create([
            'username' => 'obsazeny',
            'email' => 'jiny@example.test',
            'name' => 'Jiný',
            'password_hash' => Hash::make('heslo1234'),
            'role' => User::ROLE_USER,
        ]);

        $response = $this->from('/registrace')->post('/registrace', [
            'username' => 'obsazeny',
            'name' => 'Nový Uživatel',
            'email' => 'novy@example.test',
            'password' => 'heslo1234',
            'password_confirmation' => 'heslo1234',
        ]);

        $response->assertRedirect('/registrace');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function testLoginWorksWithUsernameAndEmail(): void
    {
        $user = $this->user('prihlaseni');

        $this->post('/prihlaseni', ['login' => $user->username, 'password' => 'heslo123'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function testInactiveUserCannotLogIn(): void
    {
        $user = $this->user('neaktivni');
        $user->forceFill(['is_active' => false])->save();

        $this->post('/prihlaseni', ['login' => $user->username, 'password' => 'heslo123'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function testDeactivatedUserLosesSession(): void
    {
        $user = $this->user('deaktivovany');

        $this->actingAs($user)->get('/profil')->assertOk();

        $user->forceFill(['is_active' => false, 'auth_version' => 2])->save();

        $this->actingAs($user->fresh())->get('/profil')->assertRedirect('/prihlaseni');
        $this->assertGuest();
    }

    public function testGuestsAreRedirectedFromProtectedPages(): void
    {
        $this->get('/profil')->assertRedirect('/prihlaseni');
        $this->get('/moje-udalosti')->assertRedirect('/prihlaseni');
        $this->get('/moderace')->assertRedirect('/prihlaseni');
        $this->get('/admin/uzivatele')->assertRedirect('/prihlaseni');
    }

    public function testRegularUserCannotOpenModerationOrAdmin(): void
    {
        $user = $this->user('bezny', User::ROLE_USER);

        $this->actingAs($user)->get('/moderace')->assertForbidden();
        $this->actingAs($user)->get('/admin/uzivatele')->assertForbidden();
    }

    public function testModeratorReachesModerationButNotAdmin(): void
    {
        $moderator = $this->user('mod', User::ROLE_MODERATOR);

        $this->actingAs($moderator)->get('/moderace')->assertOk();
        $this->actingAs($moderator)->get('/admin/uzivatele')->assertForbidden();
    }

    public function testAdminReachesUserAdministration(): void
    {
        $admin = $this->user('spravce', User::ROLE_ADMIN);

        $this->actingAs($admin)->get('/admin/uzivatele')->assertOk();
    }

    public function testPasswordChangeRequiresCurrentPassword(): void
    {
        $user = $this->user('heslo');

        $this->actingAs($user)->post('/profil/heslo', [
            'current_password' => 'spatne-heslo',
            'new_password' => 'noveheslo1',
            'new_password_confirmation' => 'noveheslo1',
        ])->assertSessionHasErrors('current_password');
    }

    public function testPasswordChangeInvalidatesOtherSessions(): void
    {
        $user = $this->user('zmena');
        $version = $user->auth_version;

        $this->actingAs($user)->post('/profil/heslo', [
            'current_password' => 'heslo123',
            'new_password' => 'noveheslo1',
            'new_password_confirmation' => 'noveheslo1',
        ])->assertRedirect();

        $this->assertGreaterThan($version, $user->fresh()->auth_version);
    }

    public function testEveryRenderedFormCarriesCsrfToken(): void
    {
        $user = $this->user('csrf');

        foreach (['/prihlaseni', '/registrace'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('name="_token"', (string) $html);
        }

        $html = $this->actingAs($user)->get('/profil')->assertOk()->getContent();
        $this->assertStringContainsString('name="_token"', (string) $html);
    }
}
