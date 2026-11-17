<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Services\FeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function testConfirmedParticipantCanRateFinishedEvent(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $rating = $this->service()->saveRating($event, $participant, 5);

        $this->assertSame(5, $rating->value);
    }

    public function testRatingBeforeEventEndIsRejected(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $this->confirmRegistration($event, $participant);

        $this->expectException(DomainRuleException::class);

        $this->service()->saveRating($event, $participant, 5);
    }

    public function testUnregisteredUserCannotRate(): void
    {
        $manager = $this->user('manazer');
        $stranger = $this->user('cizi');
        $event = $this->finishedEvent($manager);

        $this->expectException(DomainRuleException::class);

        $this->service()->saveRating($event, $stranger, 5);
    }

    public function testPendingRegistrationCannotRate(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->finishedEvent($manager);
        $type = $this->ticket($event, 'Základní', '250');
        $event->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => Registration::STATUS_PENDING,
            'price' => '250',
            'registered_at' => utc_now(),
        ]);

        $this->expectException(DomainRuleException::class);

        $this->service()->saveRating($event, $participant, 4);
    }

    public function testRatingOutsideRangeIsRejected(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $this->expectException(ValidationException::class);

        $this->service()->saveRating($event, $participant, 6);
    }

    public function testUserHasSingleRatingPerEvent(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $this->service()->saveRating($event, $participant, 3);
        $this->service()->saveRating($event, $participant, 5);

        $this->assertSame(1, $event->ratings()->count());
        $this->assertSame(5, $event->ratings()->first()->value);
    }

    public function testUserHasSingleCommentPerEvent(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $this->service()->saveComment($event, $participant, 'První komentář.');
        $this->service()->saveComment($event, $participant, 'Druhá verze komentáře.');

        $this->assertSame(1, $event->comments()->count());
        $this->assertSame('Druhá verze komentáře.', $event->comments()->first()->body);
    }

    public function testCommentMaximumLengthIsEnforced(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $this->expectException(ValidationException::class);

        $this->service()->saveComment($event, $participant, str_repeat('a', 2001));
    }

    public function testAuthorCanEditButNotStranger(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $stranger = $this->user('cizi');
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $comment = $this->service()->saveComment($event, $participant, 'Původní text.');
        $this->service()->updateComment($comment, $participant, 'Upravený text.');

        $this->assertSame('Upravený text.', $comment->fresh()->body);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->service()->updateComment($comment, $stranger, 'Cizí úprava.');
    }

    public function testModeratorHidesCommentWithReason(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $moderator = $this->moderator();
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $comment = $this->service()->saveComment($event, $participant, 'Nevhodný text.');
        $hidden = $this->service()->hideComment($comment, $moderator, 'Nevhodný obsah.');

        $this->assertSame(Comment::STATUS_HIDDEN, $hidden->status);
        $this->assertSame('Nevhodný obsah.', $hidden->hidden_reason);
        $this->assertSame($moderator->id, $hidden->moderated_by);
    }

    public function testHidingCommentRequiresReason(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $moderator = $this->moderator();
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $comment = $this->service()->saveComment($event, $participant, 'Text.');

        $this->expectException(ValidationException::class);

        $this->service()->hideComment($comment, $moderator, 'x');
    }

    public function testHiddenCommentIsNotPubliclyVisible(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $moderator = $this->moderator();
        $event = $this->finishedEvent($manager);
        $this->confirmRegistration($event, $participant);

        $comment = $this->service()->saveComment($event, $participant, 'Skrývaný text.');
        $this->service()->hideComment($comment, $moderator, 'Nevhodný obsah.');

        $this->get('/udalosti/' . $event->slug)
            ->assertOk()
            ->assertDontSee('Skrývaný text.');
    }

    public function testRatingFormIsShownOnlyAfterEventEnds(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $finished = $this->finishedEvent($manager);
        $this->confirmRegistration($finished, $participant);

        $this->actingAs($participant)
            ->get('/udalosti/' . $finished->slug)
            ->assertOk()
            ->assertSee('Hodnocení');
    }

    private function finishedEvent(\App\Models\User $manager): Event
    {
        return $this->event($manager, [
            'starts_at' => utc_now()->subDays(3),
            'ends_at' => utc_now()->subDays(3)->addHours(3),
        ]);
    }

    private function confirmRegistration(Event $event, \App\Models\User $participant): void
    {
        $type = $this->ticket($event, 'Vstupenka', '0');
        $event->registrations()->create([
            'user_id' => $participant->id,
            'ticket_type_id' => $type->id,
            'status' => Registration::STATUS_CONFIRMED,
            'price' => '0',
            'registered_at' => utc_now()->subDays(10),
        ]);
    }

    private function ticket(Event $event, string $name, string $price): TicketType
    {
        return $event->ticketTypes()->create(['name' => $name, 'price' => $price]);
    }

    private function service(): FeedbackService
    {
        return $this->app->make(FeedbackService::class);
    }
}
