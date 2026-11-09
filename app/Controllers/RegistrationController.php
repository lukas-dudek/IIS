<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Repositories\EventRepository;
use App\Repositories\RegistrationRepository;
use App\Services\RegistrationService;

final class RegistrationController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private EventRepository $events,
        private RegistrationRepository $registrations,
        private RegistrationService $registrationService
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        return Response::html($this->view->render('registrations/index', [
            'title' => 'Moje registrace',
            'registrations' => $this->registrations->forUser((int) $this->auth->id()),
        ]));
    }

    public function store(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $event = $this->events->findBySlug($slug);
        if ($event === null) {
            abort(404);
        }

        $ticketTypeId = (int) $request->post('ticket_type_id', 0);
        if ($ticketTypeId <= 0) {
            Flash::set('danger', 'Vyberte typ vstupenky.');

            return Response::redirect(url('/udalosti/' . $event['slug']));
        }

        try {
            $registration = $this->registrationService->register(
                (int) $event['id'],
                (int) $this->auth->id(),
                $ticketTypeId
            );
            Flash::set(
                'success',
                $registration['status'] === 'confirmed'
                    ? 'Registrace byla potvrzena.'
                    : 'Registrace byla vytvořena. Čeká na úhradu.'
            );
        } catch (ValidationException $exception) {
            Flash::set('danger', (array_values($exception->errors())[0] ?? null) ?: 'Registraci se nepodařilo vytvořit.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/udalosti/' . $event['slug']));
    }

    public function cancel(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $registration = $this->registrations->find($id);
        if ($registration === null) {
            abort(404);
        }
        $isManager = $this->auth->hasRole('admin')
            || (int) $registration['manager_id'] === (int) $this->auth->id();

        try {
            $this->registrationService->cancel(
                $id,
                (int) $this->auth->id(),
                $this->auth->hasRole('admin')
            );
            Flash::set('success', 'Registrace byla zrušena.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url(
            $isManager ? '/udalosti/' . $registration['event_id'] . '/ucastnici' : '/moje-registrace'
        ));
    }

    public function claimPayment(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->registrationService->claimPayment($id, (int) $this->auth->id());
            Flash::set('success', 'Oznámení o platbě bylo uloženo. Správce události ji potvrdí.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moje-registrace'));
    }

    public function participants(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $event = $this->events->findForManager(
            $id,
            (int) $this->auth->id(),
            $this->auth->hasRole('admin')
        );
        if ($event === null) {
            abort(404);
        }

        $statusRaw = $request->query('stav', '');
        $status = is_string($statusRaw) && in_array($statusRaw, ['pending', 'confirmed', 'cancelled'], true)
            ? $statusRaw
            : null;

        return Response::html($this->view->render('registrations/participants', [
            'title' => 'Účastníci události',
            'event' => $event,
            'registrations' => $this->registrations->forEvent($id, $status),
            'status' => $status ?? '',
        ]));
    }

    public function confirmPayment(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $registration = $this->registrations->find($id);
        if ($registration === null) {
            abort(404);
        }

        try {
            $this->registrationService->confirmPayment(
                $id,
                (int) $this->auth->id(),
                $this->auth->hasRole('admin')
            );
            Flash::set('success', 'Platba byla potvrzena.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/udalosti/' . $registration['event_id'] . '/ucastnici'));
    }

    public function rejectPayment(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $registration = $this->registrations->find($id);
        if ($registration === null) {
            abort(404);
        }

        try {
            $this->registrationService->rejectPayment(
                $id,
                (int) $this->auth->id(),
                $this->auth->hasRole('admin')
            );
            Flash::set('success', 'Platba byla odmítnuta a registrace zrušena.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/udalosti/' . $registration['event_id'] . '/ucastnici'));
    }
}
