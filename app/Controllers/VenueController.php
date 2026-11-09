<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\View;
use App\Repositories\VenueRepository;
use App\Services\VenueService;

final class VenueController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private VenueRepository $venues,
        private VenueService $venueService
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        return Response::html($this->view->render('venues/index', [
            'title' => 'Místa konání',
            'venues' => $this->venues->approved(),
        ]));
    }

    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->findApproved($id);
        if ($venue === null) {
            abort(404);
        }

        return Response::html($this->view->render('venues/show', [
            'title' => $venue['name'],
            'venue' => $venue,
            'events' => $this->venues->upcomingEvents($id),
        ]));
    }

    public function propose(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderForm(null, 'propose', [], null);
        }

        $data = venue_form_data($request);
        $errors = venue_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderForm(null, 'propose', $errors, null, 422);
        }

        try {
            $this->venueService->propose($data, $request->file('photo'), (int) $this->auth->id());
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderForm(null, 'propose', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Návrh místa byl odeslán ke schválení.');

        return Response::redirect(url('/moje-navrhy'));
    }

    public function editProposal(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->findOwned($id, (int) $this->auth->id());
        if ($venue === null) {
            abort(404);
        }
        if (!in_array($venue['status'], ['pending', 'rejected'], true)) {
            abort(403);
        }

        if (!$request->isPost()) {
            return $this->renderForm($venue, 'edit', [], null);
        }

        $data = venue_form_data($request);
        $errors = venue_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderForm($venue, 'edit', $errors, null, 422);
        }

        try {
            $this->venueService->updateProposal($id, (int) $this->auth->id(), $data, $request->file('photo'));
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderForm($venue, 'edit', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Návrh místa byl uložen a čeká na schválení.');

        return Response::redirect(url('/moje-navrhy'));
    }

    private function renderForm(
        ?array $venue,
        string $mode,
        array $errors,
        ?string $formError,
        int $status = 200
    ): Response {
        $edit = $mode === 'edit';
        $id = (int) ($venue['id'] ?? 0);

        return Response::html($this->view->render('venues/form', [
            'title' => $edit ? 'Upravit návrh místa' : 'Navrhnout místo',
            'mode' => $mode,
            'venue' => $venue,
            'errors' => $errors,
            'formError' => $formError,
            'action' => $edit ? url('/mista/' . $id . '/upravit') : url('/mista/navrhnout'),
            'heading' => $edit ? 'Upravit návrh místa' : 'Navrhnout místo',
            'submitLabel' => $edit ? 'Uložit návrh' : 'Odeslat návrh',
            'backUrl' => $edit ? url('/moje-navrhy') : url('/mista'),
            'approvalNote' => true,
        ]), $status);
    }
}
