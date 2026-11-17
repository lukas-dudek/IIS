<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\VenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class VenueController extends Controller
{
    public function __construct(private VenueService $venues)
    {
    }

    public function index(Request $request): View
    {
        $query = Venue::query()
            ->approved()
            ->withCount(['events' => fn ($builder) => $builder->approved()->upcoming()])
            ->orderBy('name');

        $city = trim((string) $request->query('mesto', ''));
        if ($city !== '') {
            $query->where('city', $city);
        }

        return view('venues.index', [
            'venues' => $query->paginate(15)->withQueryString(),
            'city' => $city,
            'cities' => Venue::query()->approved()->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function show(Request $request, Venue $venue): View
    {
        $viewer = $request->user();
        if (!$venue->isApproved() && ($viewer === null || (int) $venue->proposed_by !== $viewer->id)) {
            abort(404);
        }

        return view('venues.show', [
            'venue' => $venue,
            'events' => $venue->events()->approved()->upcoming()->orderBy('starts_at')->get(),
        ]);
    }

    public function propose(): View
    {
        return view('venues.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->venues->propose($this->validated($request), $request->file('photo'), $request->user()->id);

        return redirect()
            ->route('proposals.index')
            ->with('status', 'Návrh místa byl odeslán ke schválení.');
    }

    public function editProposal(Venue $venue): View
    {
        Gate::authorize('updateProposal', $venue);

        return view('venues.form', $this->formData($venue));
    }

    public function updateProposal(Request $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('updateProposal', $venue);

        $this->venues->updateProposal($venue, $this->validated($request), $request->file('photo'));

        return redirect()
            ->route('proposals.index')
            ->with('status', 'Návrh místa byl upraven a čeká na schválení.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Venue $venue): array
    {
        return [
            'venue' => $venue,
            'name' => old('name', $venue?->name ?? ''),
            'street' => old('street', $venue?->street ?? ''),
            'city' => old('city', $venue?->city ?? ''),
            'postalCode' => old('postal_code', $venue?->postal_code ?? ''),
            'description' => old('description', $venue?->description ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
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
}
