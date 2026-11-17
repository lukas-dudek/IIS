<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrationService
{
    public function register(Event $event, User $user, int $ticketTypeId): Registration
    {
        return DB::transaction(function () use ($event, $user, $ticketTypeId): Registration {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (!$locked->isApproved()) {
                throw new DomainRuleException('Registrace je možná jen na schválenou událost.');
            }
            if ($locked->hasStarted()) {
                throw new DomainRuleException('Registrace už není možná, událost již začala.');
            }

            $ticket = TicketType::query()->find($ticketTypeId);
            if ($ticket === null || $ticket->event_id !== $locked->id) {
                throw ValidationException::withMessages(['ticket_type_id' => 'Vyberte platný typ vstupenky.']);
            }

            $existing = Registration::query()
                ->where('event_id', $locked->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing !== null && $existing->isActive()) {
                throw ValidationException::withMessages([
                    'registration' => 'Na tuto událost už máte aktivní registraci.',
                ]);
            }

            $activeForEvent = Registration::query()->where('event_id', $locked->id)->active()->count();
            if ($locked->capacity !== null && $activeForEvent >= $locked->capacity) {
                throw new DomainRuleException('Kapacita události je plná.');
            }

            $activeForTicket = Registration::query()->where('ticket_type_id', $ticket->id)->active()->count();
            if ($ticket->capacity !== null && $activeForTicket >= $ticket->capacity) {
                throw new DomainRuleException('Kapacita tohoto typu vstupenky je plná.');
            }

            $price = (string) $ticket->price;
            $status = (float) $price > 0 ? Registration::STATUS_PENDING : Registration::STATUS_CONFIRMED;

            $attributes = [
                'ticket_type_id' => $ticket->id,
                'status' => $status,
                'price' => $price,
                'variable_symbol' => null,
                'payment_claimed_at' => null,
                'registered_at' => utc_now(),
                'decided_by' => null,
                'decided_at' => null,
                'cancelled_at' => null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $registration = $existing;
            } else {
                $registration = Registration::query()->create($attributes + [
                    'event_id' => $locked->id,
                    'user_id' => $user->id,
                ]);
            }

            if ($status === Registration::STATUS_PENDING) {
                $registration->forceFill([
                    'variable_symbol' => str_pad((string) $registration->id, 10, '0', STR_PAD_LEFT),
                ])->save();
            }

            return $registration->refresh();
        });
    }

    public function cancel(Registration $registration, User $actor): Registration
    {
        return DB::transaction(function () use ($registration, $actor): Registration {
            /** @var Registration $locked */
            $locked = Registration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();
            /** @var Event $event */
            $event = Event::query()->whereKey($locked->event_id)->lockForUpdate()->firstOrFail();

            $allowed = $actor->isAdmin()
                || $locked->user_id === $actor->id
                || $event->manager_id === $actor->id;
            if (!$allowed) {
                throw new AuthorizationException('Registraci můžete zrušit jen u vlastní účasti nebo své události.');
            }
            if (!$locked->isActive()) {
                throw new DomainRuleException('Registrace už není aktivní.');
            }
            if ($event->hasStarted()) {
                throw new DomainRuleException('Registraci lze zrušit jen do začátku události.');
            }

            $locked->forceFill([
                'status' => Registration::STATUS_CANCELLED,
                'cancelled_at' => utc_now(),
            ])->save();

            return $locked;
        });
    }

    public function claimPayment(Registration $registration, User $user): Registration
    {
        return DB::transaction(function () use ($registration, $user): Registration {
            /** @var Registration $locked */
            $locked = Registration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $user->id) {
                throw new AuthorizationException('Platbu lze oznámit jen u vlastní registrace.');
            }
            if (!$locked->isPending()) {
                throw new DomainRuleException('Platbu lze oznámit jen u registrace čekající na platbu.');
            }

            $locked->forceFill(['payment_claimed_at' => utc_now()])->save();

            return $locked;
        });
    }

    public function confirmPayment(Registration $registration, User $actor): Registration
    {
        return $this->decide(registration: $registration, actor: $actor, status: Registration::STATUS_CONFIRMED);
    }

    public function rejectPayment(Registration $registration, User $actor): Registration
    {
        return $this->decide(registration: $registration, actor: $actor, status: Registration::STATUS_CANCELLED);
    }

    private function decide(Registration $registration, User $actor, string $status): Registration
    {
        return DB::transaction(function () use ($registration, $actor, $status): Registration {
            /** @var Registration $locked */
            $locked = Registration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();
            /** @var Event $event */
            $event = Event::query()->whereKey($locked->event_id)->lockForUpdate()->firstOrFail();

            if (!$actor->isAdmin() && $event->manager_id !== $actor->id) {
                throw new AuthorizationException('Platby potvrzuje jen správce události.');
            }
            if (!$locked->isPending()) {
                throw new DomainRuleException('Registrace už nečeká na platbu.');
            }

            $attributes = [
                'status' => $status,
                'decided_by' => $actor->id,
                'decided_at' => utc_now(),
            ];
            if ($status === Registration::STATUS_CANCELLED) {
                $attributes['cancelled_at'] = utc_now();
            }

            $locked->forceFill($attributes)->save();

            return $locked;
        });
    }
}
