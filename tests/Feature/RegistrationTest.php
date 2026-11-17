<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function testFreeTicketIsConfirmedImmediately(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Vstup zdarma', '0');

        $registration = $this->service()->register($event, $participant, $type->id);

        $this->assertSame(Registration::STATUS_CONFIRMED, $registration->status);
        $this->assertSame('0.00', $registration->price);
        $this->assertNull($registration->variable_symbol);
    }

    public function testPaidTicketIsPendingWithVariableSymbol(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Základní', '250');

        $registration = $this->service()->register($event, $participant, $type->id);

        $this->assertSame(Registration::STATUS_PENDING, $registration->status);
        $this->assertSame('250.00', $registration->price);
        $this->assertSame(str_pad((string) $registration->id, 10, '0', STR_PAD_LEFT), $registration->variable_symbol);
    }

    public function testPriceSnapshotSurvivesTicketPriceChange(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Základní', '250');

        $registration = $this->service()->register($event, $participant, $type->id);
        $type->forceFill(['price' => '500'])->save();

        $this->assertSame('250.00', $registration->fresh()->price);
    }

    public function testFullEventRejectsRegistration(): void
    {
        $manager = $this->user('manazer');
        $first = $this->user('prvni');
        $second = $this->user('druhy');
        $event = $this->event($manager, ['capacity' => 1]);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $this->service()->register($event, $first, $type->id);

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('Kapacita události je plná.');

        $this->service()->register($event, $second, $type->id);
    }

    public function testCancelledRegistrationFreesCapacity(): void
    {
        $manager = $this->user('manazer');
        $first = $this->user('prvni');
        $second = $this->user('druhy');
        $event = $this->event($manager, ['capacity' => 1]);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $registration = $this->service()->register($event, $first, $type->id);
        $this->service()->cancel($registration, $first);

        $secondRegistration = $this->service()->register($event, $second, $type->id);

        $this->assertTrue($secondRegistration->isConfirmed());
    }

    public function testPendingRegistrationsCountTowardsCapacity(): void
    {
        $manager = $this->user('manazer');
        $first = $this->user('prvni');
        $second = $this->user('druhy');
        $event = $this->event($manager, ['capacity' => 1]);
        $type = $this->ticket($event, 'Základní', '250');

        $this->service()->register($event, $first, $type->id);

        $this->expectException(DomainRuleException::class);

        $this->service()->register($event, $second, $type->id);
    }

    public function testTicketTypeCapacityIsEnforced(): void
    {
        $manager = $this->user('manazer');
        $first = $this->user('prvni');
        $second = $this->user('druhy');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'VIP', '0', 1);
        $other = $this->ticket($event, 'Základní', '0');

        $this->service()->register($event, $first, $type->id);

        $this->expectException(DomainRuleException::class);
        $this->expectExceptionMessage('Kapacita tohoto typu vstupenky je plná.');

        $this->service()->register($event, $second, $type->id);
    }

    public function testDuplicateActiveRegistrationIsRejected(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $this->service()->register($event, $participant, $type->id);

        $this->expectException(ValidationException::class);

        $this->service()->register($event, $participant, $type->id);
    }

    public function testTicketFromAnotherEventIsRejected(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $foreignEvent = $this->event($manager);
        $foreignType = $this->ticket($foreignEvent, 'Vstupenka', '0');

        $this->expectException(ValidationException::class);

        $this->service()->register($event, $participant, $foreignType->id);
    }

    public function testUnapprovedEventCannotBeRegistered(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager, ['status' => Event::STATUS_PENDING]);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $this->expectException(DomainRuleException::class);

        $this->service()->register($event, $participant, $type->id);
    }

    public function testStartedEventCannotBeRegistered(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager, [
            'starts_at' => utc_now()->subHour(),
            'ends_at' => utc_now()->addHour(),
        ]);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $this->expectException(DomainRuleException::class);

        $this->service()->register($event, $participant, $type->id);
    }

    public function testPaymentConfirmationSwitchesStatusToConfirmed(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Základní', '250');

        $registration = $this->service()->register($event, $participant, $type->id);
        $confirmed = $this->service()->confirmPayment($registration, $manager);

        $this->assertSame(Registration::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame($manager->id, $confirmed->decided_by);
        $this->assertNotNull($confirmed->decided_at);
    }

    public function testForeignManagerCannotConfirmPayment(): void
    {
        $manager = $this->user('manazer');
        $stranger = $this->user('cizi');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Základní', '250');

        $registration = $this->service()->register($event, $participant, $type->id);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->service()->confirmPayment($registration, $stranger);
    }

    public function testRejectingPaymentCancelsRegistration(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Základní', '250');

        $registration = $this->service()->register($event, $participant, $type->id);
        $rejected = $this->service()->rejectPayment($registration, $manager);

        $this->assertSame(Registration::STATUS_CANCELLED, $rejected->status);
        $this->assertNotNull($rejected->cancelled_at);
    }

    public function testForeignUserCannotCancelRegistration(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $stranger = $this->user('cizi');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $registration = $this->service()->register($event, $participant, $type->id);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->service()->cancel($registration, $stranger);
    }

    public function testManagerCanCancelParticipantRegistration(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $registration = $this->service()->register($event, $participant, $type->id);
        $cancelled = $this->service()->cancel($registration, $manager);

        $this->assertFalse($cancelled->isActive());
    }

    public function testReRegistrationReusesCancelledRow(): void
    {
        $manager = $this->user('manazer');
        $participant = $this->user('ucastnik');
        $event = $this->event($manager);
        $type = $this->ticket($event, 'Vstupenka', '0');

        $registration = $this->service()->register($event, $participant, $type->id);
        $this->service()->cancel($registration, $participant);
        $second = $this->service()->register($event, $participant, $type->id);

        $this->assertSame($registration->id, $second->id);
        $this->assertSame(Registration::STATUS_CONFIRMED, $second->status);
        $this->assertSame(1, Registration::query()->count());
    }

    public function testParticipantListIsLimitedToManager(): void
    {
        $manager = $this->user('manazer');
        $stranger = $this->user('cizi');
        $event = $this->event($manager);

        $this->actingAs($manager)->get('/udalosti/' . $event->id . '/ucastnici')->assertOk();
        $this->actingAs($stranger)->get('/udalosti/' . $event->id . '/ucastnici')->assertForbidden();
    }

    private function ticket(Event $event, string $name, string $price, ?int $capacity = null): TicketType
    {
        return $event->ticketTypes()->create([
            'name' => $name,
            'price' => $price,
            'capacity' => $capacity,
        ]);
    }

    private function service(): RegistrationService
    {
        return $this->app->make(RegistrationService::class);
    }
}
