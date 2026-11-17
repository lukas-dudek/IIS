<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Repositories\EventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CalendarAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function testCalendarShowsOnlyOwnEvents(): void
    {
        $participant = $this->user('ucastnik');
        $stranger = $this->user('cizi');
        $manager = $this->user('manazer');

        $mine = $this->event($manager, ['title' => 'Moje akce v kalendáři']);
        $foreign = $this->event($manager, ['title' => 'Cizí akce v kalendáři']);

        $type = $mine->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0']);
        $mine->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => Registration::STATUS_CONFIRMED,
            'price' => '0',
            'registered_at' => utc_now(),
        ]);

        $this->actingAs($participant)
            ->get('/muj-kalendar')
            ->assertOk()
            ->assertSee('Moje akce v kalendáři')
            ->assertDontSee('Cizí akce v kalendáři');

        $this->actingAs($stranger)
            ->get('/muj-kalendar')
            ->assertOk()
            ->assertDontSee('Cizí akce v kalendáři');
    }

    public function testManagerSeesOwnEventInCalendar(): void
    {
        $manager = $this->user('manazer');
        $event = $this->event($manager, ['title' => 'Akce správce']);

        $this->actingAs($manager)
            ->get('/muj-kalendar')
            ->assertOk()
            ->assertSee('Akce správce');
    }

    public function testIcsExportContainsConfirmedEvents(): void
    {
        $participant = $this->user('ucastnik');
        $manager = $this->user('manazer');
        $event = $this->event($manager, ['title' => 'Koncert v exportu']);

        $type = $event->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0']);
        $event->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => Registration::STATUS_CONFIRMED,
            'price' => '0',
            'registered_at' => utc_now(),
        ]);

        $response = $this->actingAs($participant)->get('/muj-kalendar.ics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Koncert v exportu', $body);
        $this->assertStringContainsString('UID:event-' . $event->id . '@', $body);
        $this->assertMatchesRegularExpression('/DTSTART:\d{8}T\d{6}Z/', $body);
    }

    public function testCancelledEventIsMissingFromExport(): void
    {
        $participant = $this->user('ucastnik');
        $manager = $this->user('manazer');
        $event = $this->event($manager, [
            'title' => 'Zrušená akce',
            'status' => Event::STATUS_CANCELLED,
        ]);

        $type = $event->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0']);
        $event->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => Registration::STATUS_CONFIRMED,
            'price' => '0',
            'registered_at' => utc_now(),
        ]);

        $body = (string) $this->actingAs($participant)->get('/muj-kalendar.ics')->getContent();

        $this->assertStringNotContainsString('Zrušená akce', $body);
    }

    public function testSearchFiltersByQueryAndCategory(): void
    {
        $manager = $this->user('manazer');
        $matching = $this->event($manager, ['title' => 'Jazzový večer v klubu']);
        $other = $this->event($manager, ['title' => 'Seminář o databázích']);

        $this->get('/udalosti?q=Jazz')
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($other->title);

        $this->get('/udalosti?kategorie=' . $matching->category_id)
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($other->title);
    }

    public function testSearchIncludesDescendantCategories(): void
    {
        $manager = $this->user('manazer');
        $root = $this->category($manager->id, null, 'Kultura');
        $child = $this->category($manager->id, $root->id, 'Hudba');
        $event = $this->event($manager, ['title' => 'Koncert v podkategorii', 'category_id' => $child->id]);

        $this->get('/udalosti?kategorie=' . $root->id)
            ->assertOk()
            ->assertSee($event->title);
    }

    public function testFullEventFilterFindsOnlyFullEvents(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');

        $full = $this->event($manager, ['title' => 'Obsazená událost', 'capacity' => 1]);
        $free = $this->event($manager, ['title' => 'Volná událost', 'capacity' => 10]);

        $type = $full->ticketTypes()->create(['name' => 'Vstupenka', 'price' => '0']);
        $full->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => Registration::STATUS_PENDING,
            'price' => '0',
            'registered_at' => utc_now(),
        ]);

        $this->get('/udalosti?dostupnost=full')
            ->assertOk()
            ->assertSee($full->title)
            ->assertDontSee($free->title);

        $this->get('/udalosti?dostupnost=available')
            ->assertOk()
            ->assertSee($free->title)
            ->assertDontSee($full->title);
    }

    public function testArchiveFilterIncludesPastEvents(): void
    {
        $manager = $this->user('manazer');
        $past = $this->event($manager, [
            'title' => 'Proběhlá událost',
            'starts_at' => utc_now()->subDays(20),
            'ends_at' => utc_now()->subDays(20)->addHours(2),
        ]);

        $this->get('/udalosti')->assertOk()->assertDontSee($past->title);
        $this->get('/udalosti?archiv=1')->assertOk()->assertSee($past->title);
    }

    public function testSearchEscapesLikeWildcards(): void
    {
        $manager = $this->user('manazer');
        $this->event($manager, ['title' => 'Procenta 100% účast']);
        $this->event($manager, ['title' => 'Úplně jiná událost']);

        $repository = $this->app->make(EventRepository::class);
        $result = $repository->search([
            'q' => '100%',
            'category_ids' => [],
            'city' => '',
            'from' => null,
            'to' => null,
            'price' => '',
            'availability' => '',
            'archive' => false,
            'sort' => 'date',
        ]);

        $this->assertSame(1, $result->total());
    }
}
