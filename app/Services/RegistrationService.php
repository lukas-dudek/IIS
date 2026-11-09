<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\TicketTypeRepository;
use RuntimeException;

final class RegistrationService
{
    public function __construct(
        private Database $database,
        private EventRepository $events,
        private TicketTypeRepository $tickets,
        private RegistrationRepository $registrations
    ) {
    }

    public function register(int $eventId, int $userId, int $ticketTypeId): array
    {
        return $this->database->transaction(function () use ($eventId, $userId, $ticketTypeId): array {
            $event = $this->events->lock($eventId);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            if ($event['status'] !== 'approved') {
                throw new BusinessRuleException('Registrace je možná jen na schválenou událost.');
            }
            if ($event['starts_at'] <= now_utc()) {
                throw new BusinessRuleException('Registrace už není možná, událost již začala.');
            }

            $ticket = $this->tickets->find($ticketTypeId);
            if ($ticket === null || (int) $ticket['event_id'] !== $eventId) {
                throw new ValidationException(['ticket_type_id' => 'Vyberte platný typ vstupenky.']);
            }

            $existing = $this->registrations->findForEventUser($eventId, $userId);
            if ($existing !== null && in_array($existing['status'], ['pending', 'confirmed'], true)) {
                throw new ValidationException(['registration' => 'Na tuto událost už máte aktivní registraci.']);
            }

            $activeForEvent = $this->events->activeRegistrationCount($eventId);
            if ($event['capacity'] !== null && $activeForEvent >= (int) $event['capacity']) {
                throw new BusinessRuleException('Kapacita události je plná.');
            }

            $activeForTicket = $this->tickets->activeRegistrationCount($ticketTypeId);
            if ($ticket['capacity'] !== null && $activeForTicket >= (int) $ticket['capacity']) {
                throw new BusinessRuleException('Kapacita tohoto typu vstupenky je plná.');
            }

            $price = (string) $ticket['price'];
            $status = (float) $price > 0 ? 'pending' : 'confirmed';

            if ($existing !== null) {
                $id = (int) $existing['id'];
                $this->registrations->reuse($id, $ticketTypeId, $status, $price);
            } else {
                $id = $this->registrations->create($eventId, $userId, $ticketTypeId, $status, $price);
            }

            if ($status === 'pending') {
                $this->registrations->setVariableSymbol($id, str_pad((string) $id, 10, '0', STR_PAD_LEFT));
            }

            $registration = $this->registrations->find($id);
            if ($registration === null) {
                throw new RuntimeException('Registraci se nepodařilo načíst.');
            }

            return $registration;
        });
    }

    public function cancel(int $id, int $userId, bool $isAdmin): void
    {
        $this->database->transaction(function () use ($id, $userId, $isAdmin): void {
            $initial = $this->registrations->find($id);
            if ($initial === null) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }

            $event = $this->events->lock((int) $initial['event_id']);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            $registration = $this->registrations->lock($id);
            if ($registration === null) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }

            $allowed = $isAdmin
                || (int) $registration['user_id'] === $userId
                || (int) $event['manager_id'] === $userId;
            if (!$allowed) {
                throw new HttpException(403, 'Registraci můžete zrušit jen u vlastní účasti nebo své události.');
            }
            if (!in_array($registration['status'], ['pending', 'confirmed'], true)) {
                throw new BusinessRuleException('Registrace už není aktivní.');
            }
            if ($event['starts_at'] <= now_utc()) {
                throw new BusinessRuleException('Registraci lze zrušit jen do začátku události.');
            }

            $this->registrations->cancel($id);
        });
    }

    public function claimPayment(int $id, int $userId): void
    {
        $this->database->transaction(function () use ($id, $userId): void {
            $registration = $this->registrations->lock($id);
            if ($registration === null || (int) $registration['user_id'] !== $userId) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }
            if ($registration['status'] !== 'pending') {
                throw new BusinessRuleException('Platbu lze oznámit jen u registrace čekající na platbu.');
            }

            $this->registrations->claimPayment($id);
        });
    }

    public function confirmPayment(int $id, int $managerId, bool $isAdmin): void
    {
        $this->database->transaction(function () use ($id, $managerId, $isAdmin): void {
            $initial = $this->registrations->findForManager($id, $managerId, $isAdmin);
            if ($initial === null) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }
            $event = $this->events->lock((int) $initial['event_id']);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            $registration = $this->registrations->lock($id);
            if ($registration === null) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }
            if ($registration['status'] !== 'pending') {
                throw new BusinessRuleException('Registrace už nečeká na platbu.');
            }

            $this->registrations->confirm($id, $managerId);
        });
    }

    public function rejectPayment(int $id, int $managerId, bool $isAdmin): void
    {
        $this->database->transaction(function () use ($id, $managerId, $isAdmin): void {
            $initial = $this->registrations->findForManager($id, $managerId, $isAdmin);
            if ($initial === null) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }
            $event = $this->events->lock((int) $initial['event_id']);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            $registration = $this->registrations->lock($id);
            if ($registration === null) {
                throw new HttpException(404, 'Registrace nenalezena.');
            }
            if ($registration['status'] !== 'pending') {
                throw new BusinessRuleException('Registrace už nečeká na platbu.');
            }

            $this->registrations->reject($id, $managerId);
        });
    }
}
