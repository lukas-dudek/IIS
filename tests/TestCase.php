<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function admin(): User
    {
        return $this->user('admin', User::ROLE_ADMIN);
    }

    protected function moderator(): User
    {
        return $this->user('moderator', User::ROLE_MODERATOR);
    }

    protected function user(string $username = 'user', string $role = User::ROLE_USER): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create([
            'username' => $username . $counter,
            'email' => $username . $counter . '@example.test',
            'name' => 'Testovací ' . $username . ' ' . $counter,
            'password_hash' => Hash::make('heslo123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function venue(int $proposerId): Venue
    {
        static $counter = 0;
        $counter++;

        return Venue::query()->create([
            'name' => 'Místo ' . $counter,
            'street' => 'Ulice ' . $counter,
            'city' => 'Brno',
            'postal_code' => '602 00',
            'status' => Venue::STATUS_APPROVED,
            'proposed_by' => $proposerId,
        ]);
    }

    protected function category(int $proposerId, ?int $parentId = null, string $name = 'Kategorie'): Category
    {
        static $counter = 0;
        $counter++;
        $name .= ' ' . $counter;

        return Category::query()->create([
            'name' => $name,
            'slug' => 'kategorie-' . $counter,
            'parent_id' => $parentId,
            'status' => Category::STATUS_APPROVED,
            'proposed_by' => $proposerId,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function event(User $manager, array $overrides = []): Event
    {
        static $counter = 0;
        $counter++;

        $startsAt = $overrides['starts_at'] ?? utc_now()->addDays(10);

        return Event::query()->create(array_merge([
            'slug' => 'udalost-' . $counter,
            'title' => 'Událost ' . $counter,
            'description' => 'Popis testovací události s dostatečnou délkou.',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(3),
            'venue_id' => $this->venue($manager->id)->id,
            'category_id' => $this->category($manager->id)->id,
            'capacity' => null,
            'status' => Event::STATUS_APPROVED,
            'manager_id' => $manager->id,
            'submitted_at' => utc_now()->subDay(),
        ], $overrides));
    }
}
