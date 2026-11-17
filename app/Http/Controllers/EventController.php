<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use App\Repositories\EventRepository;
use App\Services\CategoryService;
use App\Services\EventService;
use App\Support\ImageUpload;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class EventController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private CategoryService $categories,
        private EventService $eventService,
        private ImageUpload $uploads
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('events.index', [
            'events' => $this->events->search($filters),
            'filters' => $filters,
            'tree' => $this->categories->approvedTree(),
            'cities' => $this->events->cities(),
            'sort' => $filters['sort'],
        ]);
    }

    public function show(Event $event): View
    {
        $viewer = auth()->user();

        if (!$event->isApproved() && ($viewer === null || !$event->canBeEditedBy($viewer))) {
            abort(404);
        }

        $event->load([
            'venue',
            'category',
            'manager',
            'ticketTypes' => fn ($query) => $query->orderBy('price'),
            'registrations' => fn ($query) => $query->whereIn('status', ['pending', 'confirmed']),
            'comments' => fn ($query) => $query->visible()->with('author')->latest(),
            'ratings',
        ]);

        $registration = $viewer === null ? null : $event->registrations
            ->firstWhere('user_id', $viewer->id);

        $activeCount = $event->registrations->whereIn('status', ['pending', 'confirmed'])->count();
        $ratingAverage = $event->ratings->isEmpty() ? null : round((float) $event->ratings->avg('value'), 1);

        return view('events.show', [
            'event' => $event,
            'registration' => $registration,
            'activeCount' => $activeCount,
            'ratingAverage' => $ratingAverage,
            'myRating' => $viewer === null ? null : $event->ratings->firstWhere('user_id', $viewer->id),
            'myComment' => $viewer === null ? null : $event->comments->firstWhere('user_id', $viewer->id),
            'canFeedback' => $viewer !== null
                && $event->isApproved()
                && $event->isFinished()
                && $registration !== null
                && $registration->isConfirmed(),
        ]);
    }

    public function mine(Request $request): View
    {
        $events = Event::query()
            ->with(['venue', 'category'])
            ->withCount(['registrations as active_registrations_count' => fn ($query) => $query
                ->whereIn('status', ['pending', 'confirmed'])])
            ->where('manager_id', $request->user()->id)
            ->orderByDesc('starts_at')
            ->paginate(12);

        return view('events.mine', ['events' => $events]);
    }

    public function create(): View
    {
        return view('events.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->input($request);
        $coverPath = $this->storeCover($request);
        $data['cover_path'] = $coverPath;

        try {
            $event = $this->eventService->create(
                $request->user()->id,
                $data,
                $this->ticketInput($request),
                $request->boolean('submit')
            );
        } catch (\Throwable $exception) {
            $this->uploads->delete($coverPath);
            throw $exception;
        }

        return redirect()
            ->route('events.show', $event->slug)
            ->with('status', $request->boolean('submit')
                ? 'Událost byla odeslána ke schválení.'
                : 'Koncept události byl uložen.');
    }

    public function edit(Event $event): View
    {
        Gate::authorize('manage', $event);
        if ($event->isCancelled()) {
            abort(403, 'Zrušenou událost nelze upravovat.');
        }

        $event->load('ticketTypes');

        return view('events.form', $this->formData($event));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);

        $data = $this->input($request);
        $previousCover = $event->cover_path;
        $coverPath = $this->storeCover($request, $event);
        $data['cover_path'] = $coverPath;

        try {
            $updated = $this->eventService->update(
                $event,
                $data,
                $this->ticketInput($request),
                $request->boolean('submit')
            );
        } catch (\Throwable $exception) {
            if ($coverPath !== $previousCover) {
                $this->uploads->delete($coverPath);
            }
            throw $exception;
        }

        if ($coverPath !== $previousCover) {
            $this->uploads->delete($previousCover);
        }

        $message = 'Změny byly uloženy.';
        if ($updated->status === Event::STATUS_PENDING && $submit) {
            $message = 'Událost byla odeslána ke schválení.';
        } elseif ($updated->status === Event::STATUS_PENDING) {
            $message = 'Podstatná změna vrátila událost ke schválení.';
        }

        return redirect()->route('events.show', $updated->slug)->with('status', $message);
    }

    public function submit(Event $event): RedirectResponse
    {
        Gate::authorize('submit', $event);

        $this->eventService->submit($event);

        return redirect()
            ->route('events.show', $event->slug)
            ->with('status', 'Událost byla odeslána ke schválení.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('cancel', $event);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'důvod zrušení']);

        $this->eventService->cancel($event, $validated['reason']);

        return redirect()
            ->route('events.show', $event->slug)
            ->with('status', 'Událost byla zrušena a aktivní registrace byly zrušeny.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Event $event): array
    {
        $defaults = default_event_times();

        return [
            'event' => $event,
            'title' => old('title', $event?->title ?? ''),
            'description' => old('description', $event?->description ?? ''),
            'startsAt' => old('starts_at', $event === null
                ? $defaults['starts_at']
                : prague_input_value($event->starts_at)),
            'endsAt' => old('ends_at', $event === null
                ? $defaults['ends_at']
                : prague_input_value($event->ends_at)),
            'venueId' => old('venue_id', $event?->venue_id),
            'categoryId' => old('category_id', $event?->category_id),
            'capacity' => old('capacity', $event?->capacity),
            'paymentNote' => old('payment_note', $event?->payment_note ?? ''),
            'venues' => Venue::query()->approved()->orderBy('name')->get(),
            'categories' => $this->categories->approvedOptions(),
            'tickets' => $this->ticketRows($event),
        ];
    }

    /**
     * @return array<int, array{id: ?int, name: string, price: string, capacity: string}>
     */
    private function ticketRows(?Event $event): array
    {
        $old = old('tickets');
        if (is_array($old)) {
            $rows = [];
            foreach ($old as $row) {
                $rows[] = [
                    'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                    'name' => (string) ($row['name'] ?? ''),
                    'price' => (string) ($row['price'] ?? ''),
                    'capacity' => (string) ($row['capacity'] ?? ''),
                ];
            }

            return $rows;
        }

        if ($event !== null) {
            return $event->ticketTypes->map(static fn ($type): array => [
                'id' => (int) $type->id,
                'name' => (string) $type->name,
                'price' => number_format((float) $type->price, 2, ',', ' '),
                'capacity' => $type->capacity === null ? '' : (string) $type->capacity,
            ])->all();
        }

        return [['id' => null, 'name' => 'Vstup zdarma', 'price' => '0', 'capacity' => '']];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $request->merge([
            'starts_at' => filled($request->input('starts_at'))
                ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $request->input('starts_at'), 'Europe/Prague')
                    ?->setTimezone('UTC')
                : null,
            'ends_at' => filled($request->input('ends_at'))
                ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $request->input('ends_at'), 'Europe/Prague')
                    ?->setTimezone('UTC')
                : null,
        ]);

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:3', 'max:140'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'venue_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'tickets' => ['array'],
        ], [
            'cover.image' => 'Nahrajte obrázek ve formátu JPEG, PNG nebo WebP.',
            'cover.mimes' => 'Nahrajte obrázek ve formátu JPEG, PNG nebo WebP.',
            'cover.max' => 'Obrázek může mít nejvýše 2 MB.',
        ], [
            'title' => 'název',
            'description' => 'popis',
            'starts_at' => 'začátek',
            'ends_at' => 'konec',
            'venue_id' => 'místo konání',
            'category_id' => 'kategorie',
            'capacity' => 'kapacita',
            'payment_note' => 'platební instrukce',
        ]);

        $validator->validate();

        return [
            'title' => (string) $request->input('title'),
            'description' => (string) $request->input('description'),
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
            'venue_id' => (int) $request->input('venue_id'),
            'category_id' => (int) $request->input('category_id'),
            'capacity' => $request->filled('capacity') ? (int) $request->input('capacity') : null,
            'payment_note' => (string) $request->input('payment_note', ''),
        ];
    }

    /**
     * @return array<int, array{id: ?int, name: string, price: string, capacity: ?string}>
     */
    private function ticketInput(Request $request): array
    {
        $rows = [];
        foreach ((array) $request->input('tickets', []) as $row) {
            $rows[] = [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'name' => (string) ($row['name'] ?? ''),
                'price' => (string) ($row['price'] ?? '0'),
                'capacity' => isset($row['capacity']) && $row['capacity'] !== '' ? (string) $row['capacity'] : null,
            ];
        }

        return $rows;
    }

    private function storeCover(Request $request, ?Event $event = null): ?string
    {
        $file = $request->file('cover');

        return $file === null ? $event?->cover_path : $this->uploads->store($file, 'covers');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $categoryId = (int) $request->query('kategorie', 0);
        $categoryIds = [];
        if ($categoryId > 0) {
            $category = Category::query()->find($categoryId);
            if ($category !== null) {
                $categoryIds = array_merge([$categoryId], $this->categories->subtreeIds($categoryId));
            }
        }

        $from = $this->parseDay((string) $request->query('od', ''), false);
        $to = $this->parseDay((string) $request->query('do', ''), true);
        if ($from !== null && $to !== null && $from->gt($to)) {
            $from = null;
            $to = null;
        }

        $price = (string) $request->query('vstupne', '');
        $availability = (string) $request->query('dostupnost', '');
        $sort = (string) $request->query('razeni', 'date');

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'category_ids' => $categoryIds,
            'city' => (string) $request->query('mesto', ''),
            'from' => $from,
            'to' => $to,
            'price' => in_array($price, ['free', 'paid'], true) ? $price : '',
            'availability' => in_array($availability, ['available', 'full'], true) ? $availability : '',
            'archive' => $request->query('archiv') === '1',
            'sort' => in_array($sort, ['date', 'price', 'title'], true) ? $sort : 'date',
        ];
    }

    private function parseDay(string $value, bool $endOfDay): ?CarbonImmutable
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Europe/Prague');
        if ($date === false) {
            return null;
        }

        return ($endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0))->setTimezone('UTC');
    }
}
