<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CommentRepository;
use App\Repositories\EventRepository;
use App\Repositories\TicketTypeRepository;
use App\Repositories\VenueRepository;
use App\Services\CategoryService;
use App\Services\EventService;
use App\Services\FeedbackService;
use App\Services\VenueService;

final class ModerationController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private VenueRepository $venues,
        private VenueService $venueService,
        private CategoryService $categoryService,
        private CommentRepository $comments,
        private FeedbackService $feedbackService,
        private EventRepository $events,
        private TicketTypeRepository $tickets,
        private EventService $eventService
    ) {
    }

    public function venues(Request $request, array $params): Response
    {
        return Response::html($this->view->render('moderation/venues', [
            'title' => 'Moderace míst',
            'venues' => $this->venues->allForModeration(),
        ]));
    }

    public function venue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->find($id);
        if ($venue === null) {
            abort(404);
        }

        return $this->renderVenue($venue, '', [], 200);
    }

    public function createVenue(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderVenueForm(null, 'create', [], null);
        }

        $data = venue_form_data($request);
        $errors = venue_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderVenueForm(null, 'create', $errors, null, 422);
        }

        try {
            $this->venueService->createApproved($data, $request->file('photo'), (int) $this->auth->id());
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderVenueForm(null, 'create', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Místo bylo vytvořeno a je schválené.');

        return Response::redirect(url('/moderace/mista'));
    }

    public function editVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->find($id);
        if ($venue === null) {
            abort(404);
        }

        if (!$request->isPost()) {
            return $this->renderVenueForm($venue, 'edit', [], null);
        }

        $data = venue_form_data($request);
        $errors = venue_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderVenueForm($venue, 'edit', $errors, null, 422);
        }

        try {
            $this->venueService->update($id, $data, $request->file('photo'));
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderVenueForm($venue, 'edit', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Místo bylo uloženo.');

        return Response::redirect(url('/moderace/mista/' . $id));
    }

    public function approveVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->venueService->approve($id, (int) $this->auth->id());
            Flash::set('success', 'Místo bylo schváleno.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/mista/' . $id));
    }

    public function rejectVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $venue = $this->venues->find($id);
        if ($venue === null) {
            abort(404);
        }

        $reason = trim($request->postString('reason'));
        $validator = new Validator(['reason' => $reason], ['reason' => 'Důvod zamítnutí']);
        $validator->required('reason')->length('reason', 3, 255);
        if ($validator->fails()) {
            return $this->renderVenue($venue, $reason, $validator->errors(), 422);
        }

        try {
            $this->venueService->reject($id, (int) $this->auth->id(), $reason);
            Flash::set('success', 'Místo bylo zamítnuto.');
        } catch (ValidationException $exception) {
            return $this->renderVenue($venue, $reason, $exception->errors(), 422);
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/mista/' . $id));
    }

    public function deleteVenue(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        if ($this->venueService->delete($id)) {
            Flash::set('success', 'Místo bylo smazáno.');
        } else {
            Flash::set('danger', 'Místo je použité v událostech, proto ho nelze smazat.');
        }

        return Response::redirect(url('/moderace/mista'));
    }

    public function categories(Request $request, array $params): Response
    {
        return Response::html($this->view->render('moderation/categories', [
            'title' => 'Moderace kategorií',
            'categories' => $this->categoryService->moderationFlat(),
        ]));
    }

    public function category(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->find($id);
        if ($category === null) {
            abort(404);
        }

        return $this->renderCategory($category, '', [], 200);
    }

    public function createCategory(Request $request, array $params): Response
    {
        if (!$request->isPost()) {
            return $this->renderCategoryForm(null, 'create', [], null);
        }

        $data = category_form_data($request);
        $errors = category_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderCategoryForm(null, 'create', $errors, null, 422);
        }

        try {
            $this->categoryService->createApproved($data, (int) $this->auth->id());
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderCategoryForm(null, 'create', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Kategorie byla vytvořena a je schválená.');

        return Response::redirect(url('/moderace/kategorie'));
    }

    public function editCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->find($id);
        if ($category === null) {
            abort(404);
        }

        if (!$request->isPost()) {
            return $this->renderCategoryForm($category, 'edit', [], null);
        }

        $data = category_form_data($request);
        $errors = category_form_errors($data);
        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderCategoryForm($category, 'edit', $errors, null, 422);
        }

        try {
            $this->categoryService->updateCategory($id, $data);
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderCategoryForm($category, 'edit', $exception->errors(), null, 422);
        }

        Flash::set('success', 'Kategorie byla uložena.');

        return Response::redirect(url('/moderace/kategorie/' . $id));
    }

    public function approveCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->categoryService->approve($id, (int) $this->auth->id());
            Flash::set('success', 'Kategorie byla schválena.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/kategorie/' . $id));
    }

    public function rejectCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $category = $this->categoryService->find($id);
        if ($category === null) {
            abort(404);
        }

        $reason = trim($request->postString('reason'));
        $validator = new Validator(['reason' => $reason], ['reason' => 'Důvod zamítnutí']);
        $validator->required('reason')->length('reason', 3, 255);
        if ($validator->fails()) {
            return $this->renderCategory($category, $reason, $validator->errors(), 422);
        }

        try {
            $this->categoryService->reject($id, (int) $this->auth->id(), $reason);
            Flash::set('success', 'Kategorie byla zamítnuta.');
        } catch (ValidationException $exception) {
            return $this->renderCategory($category, $reason, $exception->errors(), 422);
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/kategorie/' . $id));
    }

    public function deleteCategory(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        if ($this->categoryService->delete($id)) {
            Flash::set('success', 'Kategorie byla smazána.');
        } else {
            Flash::set('danger', 'Kategorie je použitá v událostech nebo má potomky, proto ji nelze smazat.');
        }

        return Response::redirect(url('/moderace/kategorie'));
    }

    private function renderCategoryForm(
        ?array $category,
        string $mode,
        array $errors,
        ?string $formError,
        int $status = 200
    ): Response {
        $edit = $mode === 'edit';
        $id = (int) ($category['id'] ?? 0);

        return Response::html($this->view->render('categories/form', [
            'title' => $edit ? 'Upravit kategorii' : 'Nová kategorie',
            'mode' => $mode,
            'category' => $category,
            'errors' => $errors,
            'formError' => $formError,
            'parentOptions' => $this->categoryService->approvedOptions($edit ? $id : null),
            'action' => $edit ? url('/moderace/kategorie/' . $id . '/upravit') : url('/moderace/kategorie/nova'),
            'heading' => $edit ? 'Upravit kategorii' : 'Nová kategorie',
            'submitLabel' => $edit ? 'Uložit' : 'Vytvořit kategorii',
            'backUrl' => $edit ? url('/moderace/kategorie/' . $id) : url('/moderace/kategorie'),
            'approvalNote' => false,
        ]), $status);
    }

    private function renderCategory(array $category, string $reason, array $reasonErrors, int $status): Response
    {
        return Response::html($this->view->render('moderation/category', [
            'title' => 'Moderace kategorie',
            'category' => $category,
            'reason' => $reason,
            'reasonErrors' => $reasonErrors,
        ]), $status);
    }

    public function comments(Request $request, array $params): Response
    {
        $statusRaw = $request->query('stav', '');
        $status = is_string($statusRaw) && in_array($statusRaw, ['visible', 'hidden'], true)
            ? $statusRaw
            : null;
        $eventRaw = $request->query('udalost', '');
        $eventId = is_string($eventRaw) && preg_match('/^\d+$/', $eventRaw) === 1
            ? (int) $eventRaw
            : null;

        return Response::html($this->view->render('moderation/comments', [
            'title' => 'Moderace komentářů',
            'comments' => $this->comments->forModeration($status, $eventId),
            'events' => $this->comments->moderationEvents(),
            'status' => $status ?? '',
            'eventId' => $eventId,
        ]));
    }

    public function hideComment(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->feedbackService->hideComment(
                $id,
                (int) $this->auth->id(),
                $request->postString('reason')
            );
            Flash::set('success', 'Komentář byl skryt.');
        } catch (ValidationException $exception) {
            Flash::set('danger', (array_values($exception->errors())[0] ?? null) ?: 'Komentář se nepodařilo skrýt.');
        }

        return Response::redirect(url('/moderace/komentare'));
    }

    public function showComment(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        $this->feedbackService->showComment($id, (int) $this->auth->id());
        Flash::set('success', 'Komentář byl zobrazen.');

        return Response::redirect(url('/moderace/komentare'));
    }

    public function index(Request $request, array $params): Response
    {
        $pendingEvents = $this->events->pendingForModeration();
        $pendingVenues = array_values(array_filter(
            $this->venues->allForModeration(),
            static fn (array $venue): bool => $venue['status'] === 'pending'
        ));
        $pendingCategories = array_values(array_filter(
            $this->categoryService->moderationFlat(),
            static fn (array $category): bool => $category['status'] === 'pending'
        ));
        $hiddenComments = $this->comments->forModeration('hidden', null);

        return Response::html($this->view->render('moderation/index', [
            'title' => 'Moderace',
            'pendingEventsCount' => count($pendingEvents),
            'pendingVenuesCount' => count($pendingVenues),
            'pendingCategoriesCount' => count($pendingCategories),
            'hiddenCommentsCount' => count($hiddenComments),
        ]));
    }

    public function events(Request $request, array $params): Response
    {
        $events = $this->events->pendingForModeration();
        foreach ($events as &$event) {
            $event['tickets'] = $this->tickets->forEvent((int) $event['id']);
            $event['collisions'] = $this->events->collisionsForEvent($event);
            $event['active_registrations'] = $this->events->activeRegistrationCount((int) $event['id']);
        }
        unset($event);

        return Response::html($this->view->render('moderation/events', [
            'title' => 'Moderace událostí',
            'events' => $events,
        ]));
    }

    public function event(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $event = $this->events->findForModeration($id);
        if ($event === null) {
            abort(404);
        }

        return $this->renderEvent(
            $event,
            $this->tickets->forEventWithAvailability($id),
            $this->events->collisionsForEvent($event),
            '',
            [],
            200
        );
    }

    public function approveEvent(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        try {
            $this->eventService->approve($id, (int) $this->auth->id());
            Flash::set('success', 'Událost byla schválena.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moderace/udalosti/' . $id));
    }

    public function rejectEvent(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $event = $this->events->findForModeration($id);
        if ($event === null) {
            abort(404);
        }
        $reason = trim($request->postString('reason'));
        $validator = new Validator(['reason' => $reason], ['reason' => 'Důvod zamítnutí']);
        $validator->required('reason')->length('reason', 3, 255);
        if ($validator->fails()) {
            return $this->renderEvent(
                $event,
                $this->tickets->forEventWithAvailability($id),
                $this->events->collisionsForEvent($event),
                $reason,
                $validator->errors(),
                422
            );
        }

        try {
            $this->eventService->reject($id, (int) $this->auth->id(), $reason);
            Flash::set('success', 'Událost byla zamítnuta.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        } catch (ValidationException $exception) {
            return $this->renderEvent(
                $event,
                $this->tickets->forEventWithAvailability($id),
                $this->events->collisionsForEvent($event),
                $reason,
                $exception->errors(),
                422
            );
        }

        return Response::redirect(url('/moderace/udalosti/' . $id));
    }

    private function renderEvent(
        array $event,
        array $tickets,
        array $collisions,
        string $reason,
        array $reasonErrors,
        int $status
    ): Response {
        $event['active_registrations'] = $this->events->activeRegistrationCount((int) $event['id']);

        return Response::html($this->view->render('moderation/event', [
            'title' => 'Moderace události',
            'event' => $event,
            'tickets' => $tickets,
            'collisions' => $collisions,
            'reason' => $reason,
            'reasonErrors' => $reasonErrors,
        ]), $status);
    }

    private function renderVenueForm(
        ?array $venue,
        string $mode,
        array $errors,
        ?string $formError,
        int $status = 200
    ): Response {
        $edit = $mode === 'edit';
        $id = (int) ($venue['id'] ?? 0);

        return Response::html($this->view->render('venues/form', [
            'title' => $edit ? 'Upravit místo' : 'Nové místo',
            'mode' => $mode,
            'venue' => $venue,
            'errors' => $errors,
            'formError' => $formError,
            'action' => $edit ? url('/moderace/mista/' . $id . '/upravit') : url('/moderace/mista/novy'),
            'heading' => $edit ? 'Upravit místo' : 'Nové místo',
            'submitLabel' => $edit ? 'Uložit' : 'Vytvořit místo',
            'backUrl' => $edit ? url('/moderace/mista/' . $id) : url('/moderace/mista'),
            'approvalNote' => false,
        ]), $status);
    }

    private function renderVenue(array $venue, string $reason, array $reasonErrors, int $status): Response
    {
        return Response::html($this->view->render('moderation/venue', [
            'title' => 'Moderace místa',
            'venue' => $venue,
            'reason' => $reason,
            'reasonErrors' => $reasonErrors,
        ]), $status);
    }
}
