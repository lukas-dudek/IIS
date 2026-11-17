<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function testManagerCreatesDraftEvent(): void
    {
        $manager = $this->user('manazer');
        $data = $this->formData($manager);

        $event = $this->service()->create($manager->id, $data, [
            ['id' => null, 'name' => 'Vstup zdarma', 'price' => '0', 'capacity' => null],
        ], false);

        $this->assertSame(Event::STATUS_DRAFT, $event->status);
        $this->assertSame($manager->id, $event->manager_id);
        $this->assertNull($event->submitted_at);
        $this->assertCount(1, $event->ticketTypes);
    }

    public function testSubmittingDraftMovesItToPending(): void
    {
        $manager = $this->user('manazer');

        $event = $this->service()->create($manager->id, $this->formData($manager), [
            ['id' => null, 'name' => 'Vstupenka', 'price' => '100', 'capacity' => null],
        ], false);

        $submitted = $this->service()->submit($event);

        $this->assertSame(Event::STATUS_PENDING, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    public function testSubmittedEventRequiresFutureStart(): void
    {
        $manager = $this->user('manazer');
        $data = $this->formData($manager);
        $data['starts_at'] = utc_now()->subHours(2);
        $data['ends_at'] = utc_now()->addHour();

        $this->expectException(ValidationException::class);

        $this->service()->create($manager->id, $data, [
            ['id' => null, 'name' => 'Vstupenka', 'price' => '100', 'capacity' => null],
        ], true);
    }

    public function testEventRequiresAtLeastOneTicketWhenSubmitted(): void
    {
        $manager = $this->user('manazer');

        $this->expectException(ValidationException::class);

        $this->service()->create($manager->id, $this->formData($manager), [], true);
    }

    public function testEndMustBeAfterStart(): void
    {
        $manager = $this->user('manazer');
        $data = $this->formData($manager);
        $data['ends_at'] = $data['starts_at']->subHour();

        $this->expectException(ValidationException::class);

        $this->service()->create($manager->id, $data, [
            ['id' => null, 'name' => 'Vstupenka', 'price' => '0', 'capacity' => null],
        ], false);
    }

    public function testApprovedEventCannotBeApprovedTwice(): void
    {
        $manager = $this->user('manazer');
        $moderator = $this->user('mod', User::ROLE_MODERATOR);
        $event = $this->event($manager, ['status' => Event::STATUS_PENDING]);

        $this->service()->approve($event, $moderator->id);

        $this->expectException(\App\Exceptions\DomainRuleException::class);
        $this->service()->approve($event->fresh(), $moderator->id);
    }

    public function testManagerCannotApproveOwnEvent(): void
    {
        $moderator = $this->user('mod', User::ROLE_MODERATOR);
        $event = $this->event($moderator, ['status' => Event::STATUS_PENDING]);

        $this->expectException(\App\Exceptions\DomainRuleException::class);

        $this->service()->approve($event, $moderator->id);
    }

    public function testSignificantChangeReturnsApprovedEventToPending(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);
        $event->ticketTypes()->create(['name' => 'Základní', 'price' => '100', 'capacity' => null]);

        $data = $this->formData($manager, $event);
        $data['title'] = 'Zcela nový název události';

        $updated = $this->service()->update($event, $data, [
            ['id' => $event->ticketTypes()->first()->id, 'name' => 'Základní', 'price' => '100', 'capacity' => null],
        ], false);

        $this->assertSame(Event::STATUS_PENDING, $updated->status);
    }

    public function testDescriptionChangeKeepsApprovedStatus(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);
        $event->ticketTypes()->create(['name' => 'Základní', 'price' => '100', 'capacity' => null]);

        $data = $this->formData($manager, $event);
        $data['description'] = 'Upravený popis události bez podstatné změny.';

        $updated = $this->service()->update($event, $data, [
            ['id' => $event->ticketTypes()->first()->id, 'name' => 'Základní', 'price' => '100', 'capacity' => null],
        ], false);

        $this->assertSame(Event::STATUS_APPROVED, $updated->status);
    }

    public function testTicketPriceChangeCountsAsSignificantChange(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);
        $type = $event->ticketTypes()->create(['name' => 'Základní', 'price' => '100', 'capacity' => null]);

        $data = $this->formData($manager, $event);

        $updated = $this->service()->update($event, $data, [
            ['id' => $type->id, 'name' => 'Základní', 'price' => '250', 'capacity' => null],
        ], false);

        $this->assertSame(Event::STATUS_PENDING, $updated->status);
    }

    public function testCapacityCannotDropBelowActiveRegistrations(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager, ['capacity' => 5]);
        $type = $event->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0', 'capacity' => null]);

        foreach (['a', 'b', 'c'] as $username) {
            $participant = $this->user($username);
            $event->registrations()->create([
                'user_id' => $participant->id,
                'ticket_type_id' => $type->id,
                'status' => 'confirmed',
                'price' => '0',
                'registered_at' => utc_now(),
            ]);
        }

        $data = $this->formData($manager, $event);
        $data['capacity'] = 2;

        try {
            $this->service()->update($event, $data, [
                ['id' => $type->id, 'name' => 'Vstupenka', 'price' => '0', 'capacity' => null],
            ], false);
            $this->fail('Snížení kapacity pod aktivní registrace musí selhat.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capacity', $exception->errors());
        }
    }

    public function testTicketWithRegistrationsCannotBeDeleted(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);
        $type = $event->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0', 'capacity' => null]);

        $participant = $this->user('ucastnik');
        $event->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => 'confirmed',
            'price' => '0',
            'registered_at' => utc_now(),
        ]);

        try {
            $this->service()->update($event, $this->formData($manager, $event), [
                ['id' => $type->id, 'name' => '', 'price' => '0', 'capacity' => null],
            ], false);
            $this->fail('Smazání typu vstupenky s registracemi musí selhat.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_name_0', $exception->errors());
        }

        $this->assertDatabaseHas('ticket_types', ['id' => $type->id]);
    }

    public function testCancellingEventCancelsActiveRegistrations(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager);
        $type = $event->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0', 'capacity' => null]);
        $participant = $this->user('ucastnik');

        $registration = $event->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => 'confirmed',
            'price' => '0',
            'registered_at' => utc_now(),
        ]);

        $this->service()->cancel($event, 'Málo prodaných vstupenek.');

        $this->assertSame(Event::STATUS_CANCELLED, $event->fresh()->status);
        $this->assertSame('cancelled', $registration->fresh()->status);
    }

    public function testNonManagerCannotEditForeignEvent(): void
    {
        $owner = $this->user('vlastnik');
        $other = $this->user('cizi');
        $event = $this->event($owner);

        $this->actingAs($other)
            ->get('/udalosti/' . $event->id . '/upravit')
            ->assertForbidden();
    }

    public function testAdminCanEditForeignEvent(): void
    {
        $owner = $this->user('vlastnik');
        $admin = $this->user('spravce', User::ROLE_ADMIN);
        $event = $this->event($owner);

        $this->actingAs($admin)
            ->get('/udalosti/' . $event->id . '/upravit')
            ->assertOk();
    }

    public function testManagerSeesOwnDraftInMyEvents(): void
    {
        $manager = $this->user('manazer');
        $draft = $this->event($manager, ['status' => Event::STATUS_DRAFT]);

        $this->actingAs($manager)
            ->get('/moje-udalosti')
            ->assertOk()
            ->assertSee($draft->title);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $manager, ?Event $event = null): array
    {
        return [
            'title' => $event?->title ?? 'Testovací událost s názvem',
            'description' => $event?->description ?? 'Dostatečně dlouhý popis testovací události.',
            'starts_at' => $event?->starts_at ?? utc_now()->addDays(7),
            'ends_at' => $event?->ends_at ?? utc_now()->addDays(7)->addHours(2),
            'venue_id' => $event?->venue_id ?? $this->venue($manager->id)->id,
            'category_id' => $event?->category_id ?? $this->category($manager->id)->id,
            'capacity' => $event?->capacity,
            'payment_note' => null,
        ];
    }

    private function service(): EventService
    {
        return $this->app->make(EventService::class);
    }
}
