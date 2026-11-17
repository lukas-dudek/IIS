<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Venue;
use App\Services\CategoryService;
use App\Services\EventService;
use App\Services\FeedbackService;
use App\Services\VenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class ModerationController extends Controller
{
    public function __construct(
        private EventService $events,
        private VenueService $venues,
        private CategoryService $categories,
        private FeedbackService $feedback
    ) {
    }

    public function index(): View
    {
        return view('moderation.index', [
            'pendingEvents' => Event::query()->where('status', Event::STATUS_PENDING)->count(),
            'pendingVenues' => Venue::query()->pending()->count(),
            'pendingCategories' => Category::query()->where('status', Category::STATUS_PENDING)->count(),
            'hiddenComments' => Comment::query()->where('status', Comment::STATUS_HIDDEN)->count(),
        ]);
    }

    public function events(): View
    {
        return view('moderation.events', [
            'events' => Event::query()
                ->with(['venue', 'category', 'manager'])
                ->where('status', Event::STATUS_PENDING)
                ->orderBy('submitted_at')
                ->paginate(15),
        ]);
    }

    public function event(Event $event): View
    {
        $event->load(['venue', 'category', 'manager', 'ticketTypes', 'registrations']);

        return view('moderation.event', [
            'event' => $event,
            'activeCount' => $event->registrations->whereIn('status', ['pending', 'confirmed'])->count(),
        ]);
    }

    public function approveEvent(Request $request, Event $event): RedirectResponse
    {
        $this->events->approve($event, $request->user()->id);

        return redirect()
            ->route('moderation.events')
            ->with('status', 'Událost "' . $event->title . '" byla schválena.');
    }

    public function rejectEvent(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'důvod zamítnutí']);

        $this->events->reject($event, $request->user()->id, $validated['reason']);

        return redirect()
            ->route('moderation.events')
            ->with('status', 'Událost byla zamítnuta.');
    }

    public function venues(Request $request): View
    {
        $status = (string) $request->query('stav', 'pending');

        return view('moderation.venues', [
            'venues' => Venue::query()
                ->with('proposer')
                ->when(in_array($status, ['pending', 'approved', 'rejected'], true),
                    fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(20),
            'status' => $status,
        ]);
    }

    public function venue(Venue $venue): View
    {
        return view('moderation.venue', ['venue' => $venue->load(['proposer', 'reviewer'])]);
    }

    public function createVenue(): View
    {
        return view('moderation.venue-form', [
            'venue' => null,
            'name' => old('name', ''),
            'street' => old('street', ''),
            'city' => old('city', ''),
            'postalCode' => old('postal_code', ''),
            'description' => old('description', ''),
        ]);
    }

    public function storeVenue(Request $request): RedirectResponse
    {
        $this->venues->createApproved(
            $this->venueInput($request),
            $request->file('photo'),
            $request->user()->id
        );

        return redirect()->route('moderation.venues')->with('status', 'Místo bylo vytvořeno a schváleno.');
    }

    public function editVenue(Venue $venue): View
    {
        return view('moderation.venue-form', [
            'venue' => $venue,
            'name' => old('name', $venue->name),
            'street' => old('street', $venue->street),
            'city' => old('city', $venue->city),
            'postalCode' => old('postal_code', $venue->postal_code),
            'description' => old('description', $venue->description),
        ]);
    }

    public function updateVenue(Request $request, Venue $venue): RedirectResponse
    {
        $this->venues->updateDetails($venue, $this->venueInput($request), $request->file('photo'));

        return redirect()->route('moderation.venue', $venue)->with('status', 'Místo bylo upraveno.');
    }

    public function approveVenue(Request $request, Venue $venue): RedirectResponse
    {
        $this->venues->approve($venue, $request->user()->id);

        return back()->with('status', 'Místo bylo schváleno.');
    }

    public function rejectVenue(Request $request, Venue $venue): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'důvod zamítnutí']);

        $this->venues->reject($venue, $request->user()->id, $validated['reason']);

        return back()->with('status', 'Místo bylo zamítnuto.');
    }

    public function deleteVenue(Venue $venue): RedirectResponse
    {
        $this->venues->delete($venue);

        return redirect()->route('moderation.venues')->with('status', 'Místo bylo smazáno.');
    }

    public function categories(Request $request): View
    {
        $status = (string) $request->query('stav', '');

        return view('moderation.categories', [
            'rows' => $this->categories->moderationRows(),
            'pending' => Category::query()
                ->where('status', Category::STATUS_PENDING)
                ->with('proposer')
                ->orderByDesc('created_at')
                ->get(),
            'status' => $status,
        ]);
    }

    public function category(Category $category): View
    {
        return view('moderation.category', [
            'category' => $category->load(['proposer', 'reviewer', 'parent']),
            'children' => $category->children()->with('proposer')->get(),
        ]);
    }

    public function createCategory(): View
    {
        return view('moderation.category-form', [
            'category' => null,
            'name' => old('name', ''),
            'description' => old('description', ''),
            'parentId' => old('parent_id', ''),
            'options' => $this->categories->approvedOptions(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->categories->createApproved($this->categoryInput($request), $request->user()->id);

        return redirect()->route('moderation.categories')->with('status', 'Kategorie byla vytvořena a schválena.');
    }

    public function editCategory(Category $category): View
    {
        return view('moderation.category-form', [
            'category' => $category,
            'name' => old('name', $category->name),
            'description' => old('description', $category->description),
            'parentId' => old('parent_id', $category->parent_id),
            'options' => $this->categories->approvedOptions($category),
        ]);
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $this->categories->updateCategory($category, $this->categoryInput($request));

        return redirect()->route('moderation.category', $category)->with('status', 'Kategorie byla upravena.');
    }

    public function approveCategory(Request $request, Category $category): RedirectResponse
    {
        $this->categories->approve($category, $request->user()->id);

        return back()->with('status', 'Kategorie byla schválena.');
    }

    public function rejectCategory(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'důvod zamítnutí']);

        $this->categories->reject($category, $request->user()->id, $validated['reason']);

        return back()->with('status', 'Kategorie byla zamítnuta.');
    }

    public function deleteCategory(Category $category): RedirectResponse
    {
        $this->categories->delete($category);

        return redirect()->route('moderation.categories')->with('status', 'Kategorie byla smazána.');
    }

    public function comments(Request $request): View
    {
        $status = (string) $request->query('stav', '');
        $eventId = (int) $request->query('udalost', 0);

        return view('moderation.comments', [
            'comments' => Comment::query()
                ->with(['event', 'author', 'moderator'])
                ->when(in_array($status, ['visible', 'hidden'], true),
                    fn ($query) => $query->where('status', $status))
                ->when($eventId > 0, fn ($query) => $query->where('event_id', $eventId))
                ->orderByDesc('created_at')
                ->paginate(25),
            'status' => $status,
            'eventId' => $eventId,
            'events' => Event::query()->orderByDesc('starts_at')->limit(100)->get(['id', 'title', 'starts_at']),
        ]);
    }

    public function hideComment(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'důvod skrytí']);

        $this->feedback->hideComment($comment, $request->user(), $validated['reason']);

        return back()->with('status', 'Komentář byl skryt.');
    }

    public function showComment(Request $request, Comment $comment): RedirectResponse
    {
        $this->feedback->showComment($comment, $request->user());

        return back()->with('status', 'Komentář byl znovu zobrazen.');
    }

    /**
     * @return array<string, mixed>
     */
    private function venueInput(Request $request): array
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'street' => ['required', 'string', 'min:2', 'max:120'],
            'city' => ['required', 'string', 'min:2', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^\d{3}\s?\d{2}$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ], [
            'postal_code.regex' => 'PSČ zadejte ve formátu 123 45.',
            'photo.image' => 'Nahrajte obrázek ve formátu JPEG, PNG nebo WebP.',
            'photo.max' => 'Obrázek může mít nejvýše 2 MB.',
            'photo.mimes' => 'Nahrajte obrázek ve formátu JPEG, PNG nebo WebP.',
        ], [
            'name' => 'název místa',
            'street' => 'ulice',
            'city' => 'město',
            'postal_code' => 'PSČ',
            'description' => 'popis',
            'photo' => 'fotografie',
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryInput(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ], [], [
            'name' => 'název kategorie',
            'description' => 'popis',
            'parent_id' => 'rodičovská kategorie',
        ])->validate();

        $data['parent_id'] = $data['parent_id'] === null ? null : (int) $data['parent_id'];

        return $data;
    }
}
