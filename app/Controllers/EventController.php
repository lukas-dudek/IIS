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
use App\Repositories\RatingRepository;
use App\Repositories\RegistrationRepository;
use App\Repositories\TicketTypeRepository;
use App\Repositories\VenueRepository;
use App\Services\CategoryService;
use App\Services\EventService;

final class EventController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private EventRepository $events,
        private TicketTypeRepository $tickets,
        private EventService $eventService,
        private VenueRepository $venues,
        private CategoryService $categoryService,
        private CommentRepository $comments,
        private RatingRepository $ratings,
        private RegistrationRepository $registrations
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $q = mb_substr($this->queryString($request, 'q'), 0, 100);
        $categoryRaw = $this->queryString($request, 'kategorie');
        $categoryId = preg_match('/^\d+$/', $categoryRaw) === 1 ? (int) $categoryRaw : 0;
        $categoryIds = $categoryId > 0
            ? array_merge([$categoryId], $this->categoryService->descendantIds($categoryId))
            : [];
        $city = $this->queryString($request, 'mesto');

        $fromRaw = $this->queryString($request, 'od');
        $toRaw = $this->queryString($request, 'do');
        $from = $fromRaw === '' ? null : prague_day_start_utc($fromRaw);
        $to = $toRaw === '' ? null : prague_day_end_utc($toRaw);
        if ($from === null) {
            $fromRaw = '';
        }
        if ($to === null) {
            $toRaw = '';
        }
        if ($from !== null && $to !== null && $from > $to) {
            $from = null;
            $to = null;
            $fromRaw = '';
            $toRaw = '';
        }

        $priceRaw = $this->queryString($request, 'vstupne');
        $price = in_array($priceRaw, ['free', 'paid'], true) ? $priceRaw : '';
        $availabilityRaw = $this->queryString($request, 'dostupnost');
        $availability = in_array($availabilityRaw, ['available', 'full'], true) ? $availabilityRaw : '';
        $archive = $this->queryString($request, 'archiv') === '1';
        $sortRaw = $this->queryString($request, 'razeni');
        $sort = in_array($sortRaw, ['date', 'price', 'title'], true) ? $sortRaw : 'date';
        $pageRaw = $this->queryString($request, 'strana');
        $page = preg_match('/^\d+$/', $pageRaw) === 1 ? max(1, (int) $pageRaw) : 1;
        $perPage = 12;

        $filters = [
            'q' => $q,
            'category_ids' => $categoryIds,
            'city' => $city,
            'from' => $from,
            'to' => $to,
            'price' => $price,
            'availability' => $availability,
            'archive' => $archive,
            'sort' => $sort,
        ];

        $result = $this->events->search($filters, $page, $perPage);
        $totalPages = max(1, (int) ceil($result['total'] / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $result = $this->events->search($filters, $page, $perPage);
        }

        return Response::html($this->view->render('events/index', [
            'title' => 'Události',
            'events' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'totalPages' => $totalPages,
            'q' => $q,
            'categoryId' => $categoryId,
            'city' => $city,
            'from' => $fromRaw,
            'to' => $toRaw,
            'price' => $price,
            'availability' => $availability,
            'archive' => $archive,
            'sort' => $sort,
            'categories' => $this->categoryService->approvedOptions(),
            'cities' => $this->events->cities(),
        ]));
    }

    public function show(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $event = $this->events->findBySlug($slug);
        if ($event === null) {
            abort(404);
        }

        return Response::html($this->view->render('events/show', [
            'title' => $event['title'],
            'event' => $event,
            'tickets' => $this->tickets->forEventWithAvailability((int) $event['id']),
            'rating' => $this->ratings->summaryForEvent((int) $event['id']),
            'comments' => $this->comments->visibleForEvent((int) $event['id']),
            'commentsOpen' => is_past($event['ends_at']),
            'registration' => $this->auth->check()
                ? $this->registrations->findForEventUser((int) $event['id'], (int) $this->auth->id())
                : null,
        ]));
    }

    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');

        return is_string($value) ? trim($value) : '';
    }

    public function mine(Request $request, array $params): Response
    {
        return Response::html($this->view->render('events/mine', [
            'title' => 'Moje události',
            'events' => $this->events->mine((int) $this->auth->id()),
        ]));
    }

    public function create(Request $request, array $params): Response
    {
        $times = default_event_times();

        return $this->renderForm(null, [
            'title' => '',
            'description' => '',
            'starts_at' => $times['starts_at'],
            'ends_at' => $times['ends_at'],
            'venue_id' => '',
            'category_id' => '',
            'capacity' => '',
            'payment_note' => '',
        ], $this->blankTicketRows(), [], null, 'draft', null);
    }

    public function store(Request $request, array $params): Response
    {
        return $this->handleSave(null, $request);
    }

    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $event = $this->findForManager($id);

        if ($request->isPost()) {
            return $this->handleSave($id, $request);
        }

        return $this->renderForm($event, [
            'title' => $event['title'],
            'description' => $event['description'],
            'starts_at' => prague_input_value($event['starts_at']),
            'ends_at' => prague_input_value($event['ends_at']),
            'venue_id' => (string) $event['venue_id'],
            'category_id' => (string) $event['category_id'],
            'capacity' => $event['capacity'] === null ? '' : (string) $event['capacity'],
            'payment_note' => $event['payment_note'] ?? '',
        ], $this->ticketRowsForEvent($id), [], null, $event['status'], $event['rejection_reason']);
    }

    public function update(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        return $this->handleSave($id, $request, false);
    }

    public function submit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }

        return $this->handleSave($id, $request, true);
    }

    public function cancel(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            abort(404);
        }
        $event = $this->findForManager($id);

        $reason = trim((string) $request->post('reason', ''));
        $validator = new Validator(['reason' => $reason], ['reason' => 'Důvod zrušení']);
        $validator->required('reason')->length('reason', 3, 255);
        if ($validator->fails()) {
            Flash::set('danger', $validator->errors()['reason']);

            return Response::redirect(url('/udalosti/' . $id . '/upravit'));
        }

        try {
            $this->eventService->cancel($id, (int) $this->auth->id(), $this->auth->hasRole('admin'), $reason);
            Flash::set('success', 'Událost byla zrušena a aktivní registrace byly zrušeny.');
        } catch (BusinessRuleException $exception) {
            Flash::set('danger', $exception->getMessage());
        }

        return Response::redirect(url('/moje-udalosti'));
    }

    private function handleSave(?int $id, Request $request, ?bool $submit = null): Response
    {
        $event = null;
        if ($id !== null) {
            $event = $this->findForManager($id);
        }

        $submit ??= $request->post('action') === 'submit';
        $data = event_form_data($request);
        $ticketRows = event_ticket_rows($request);

        $errors = event_form_errors($data);
        $errors += event_ticket_errors($ticketRows);

        $startsUtc = prague_to_utc($data['starts_at']);
        $endsUtc = prague_to_utc($data['ends_at']);
        if ($startsUtc === null) {
            $errors['starts_at'] = 'Zadejte platné datum a čas.';
        }
        if ($endsUtc === null) {
            $errors['ends_at'] = 'Zadejte platné datum a čas.';
        }
        if ($startsUtc !== null && $endsUtc !== null && $endsUtc <= $startsUtc) {
            $errors['ends_at'] = 'Konec musí být po začátku.';
        }

        if ($errors !== []) {
            Session::set('_old', $data);

            return $this->renderForm(
                $event,
                $data,
                $ticketRows,
                $errors,
                null,
                $event['status'] ?? 'draft',
                $event['rejection_reason'] ?? null,
                422
            );
        }

        $normalized = [
            'title' => $data['title'],
            'description' => $data['description'],
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
            'venue_id' => (int) $data['venue_id'],
            'category_id' => (int) $data['category_id'],
            'capacity' => $data['capacity'] === '' ? null : (int) $data['capacity'],
            'payment_note' => $data['payment_note'] === '' ? null : $data['payment_note'],
        ];

        try {
            if ($event === null) {
                $this->eventService->create((int) $this->auth->id(), $normalized, $ticketRows, $submit);
            } else {
                $this->eventService->save(
                    (int) $event['id'],
                    (int) $this->auth->id(),
                    $this->auth->hasRole('admin'),
                    $normalized,
                    $ticketRows,
                    $submit
                );
            }
        } catch (ValidationException $exception) {
            Session::set('_old', $data);

            return $this->renderForm(
                $event,
                $data,
                $ticketRows,
                $exception->errors(),
                null,
                $event['status'] ?? 'draft',
                $event['rejection_reason'] ?? null,
                422
            );
        } catch (BusinessRuleException $exception) {
            return $this->renderForm(
                $event,
                $data,
                $ticketRows,
                [],
                $exception->getMessage(),
                $event['status'] ?? 'draft',
                $event['rejection_reason'] ?? null,
                422
            );
        }

        Flash::set('success', $submit ? 'Událost byla odeslána ke schválení.' : 'Událost byla uložena.');

        return Response::redirect(url('/moje-udalosti'));
    }

    private function findForManager(int $id): array
    {
        $event = $this->events->findForManager(
            $id,
            (int) $this->auth->id(),
            $this->auth->hasRole('admin')
        );
        if ($event === null) {
            abort(404);
        }

        return $event;
    }

    private function renderForm(
        ?array $event,
        array $formData,
        array $ticketRows,
        array $errors,
        ?string $formError,
        string $status,
        ?string $rejectionReason,
        int $httpStatus = 200
    ): Response {
        $eventId = $event === null ? null : (int) $event['id'];

        return Response::html($this->view->render('events/form', [
            'title' => $event === null ? 'Nová událost' : 'Upravit událost',
            'event' => $event,
            'formData' => $formData,
            'ticketRows' => $ticketRows,
            'errors' => $errors,
            'formError' => $formError,
            'status' => $status,
            'rejectionReason' => $rejectionReason,
            'venueOptions' => $this->venues->approved(),
            'categoryOptions' => $this->categoryService->approvedOptions(),
            'action' => $event === null ? url('/udalosti') : url('/udalosti/' . $eventId),
            'submitAction' => $event === null ? url('/udalosti') : url('/udalosti/' . $eventId . '/odeslat'),
        ]), $httpStatus);
    }

    private function blankTicketRows(): array
    {
        return [
            ['id' => null, 'name' => '', 'price' => '', 'capacity' => null],
            ['id' => null, 'name' => '', 'price' => '', 'capacity' => null],
            ['id' => null, 'name' => '', 'price' => '', 'capacity' => null],
        ];
    }

    private function ticketRowsForEvent(int $eventId): array
    {
        $rows = [];
        foreach ($this->tickets->forEvent($eventId) as $type) {
            $rows[] = [
                'id' => (int) $type['id'],
                'name' => $type['name'],
                'price' => $type['price'],
                'capacity' => $type['capacity'] === null ? null : (int) $type['capacity'],
            ];
        }
        while (count($rows) < 3) {
            $rows[] = ['id' => null, 'name' => '', 'price' => '', 'capacity' => null];
        }

        return $rows;
    }
}
