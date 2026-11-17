<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class RegistrationController extends Controller
{
    public function __construct(private RegistrationService $registrations)
    {
    }

    public function store(Request $request, \App\Models\Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_type_id' => ['required', 'integer'],
        ], [], ['ticket_type_id' => 'typ vstupenky']);

        $registration = $this->registrations->register($event, $request->user(), (int) $validated['ticket_type_id']);

        $message = $registration->isConfirmed()
            ? 'Registrace byla potvrzena.'
            : 'Registrace byla vytvořena, čeká na úhradu. Variabilní symbol: ' . $registration->variable_symbol;

        return redirect()->route('events.show', $event->slug)->with('status', $message);
    }

    public function index(Request $request): View
    {
        $registrations = Registration::query()
            ->with(['event.venue', 'event.category', 'ticketType'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('registered_at')
            ->paginate(15);

        return view('registrations.index', ['registrations' => $registrations]);
    }

    public function cancel(Request $request, Registration $registration): RedirectResponse
    {
        $registration->loadMissing('event');
        $this->registrations->cancel($registration, $request->user());

        return redirect()
            ->route('registrations.index')
            ->with('status', 'Registrace byla zrušena.');
    }

    public function claimPayment(Request $request, Registration $registration): RedirectResponse
    {
        $registration->loadMissing('event');
        $this->registrations->claimPayment($registration, $request->user());

        return redirect()
            ->route('registrations.index')
            ->with('status', 'Oznámili jste úhradu. Správce události ji potvrdí.');
    }

    public function participants(\App\Models\Event $event): View
    {
        Gate::authorize('viewParticipants', $event);

        return view('registrations.participants', [
            'event' => $event,
            'registrations' => $event->registrations()
                ->with(['user', 'ticketType', 'decidedBy'])
                ->orderBy('registered_at')
                ->get(),
        ]);
    }

    public function confirmPayment(Request $request, Registration $registration): RedirectResponse
    {
        $registration->loadMissing('event');
        $this->registrations->confirmPayment($registration, $request->user());

        return back()->with('status', 'Platba byla potvrzena, registrace je potvrzená.');
    }

    public function rejectPayment(Request $request, Registration $registration): RedirectResponse
    {
        $registration->loadMissing('event');
        $this->registrations->rejectPayment($registration, $request->user());

        return back()->with('status', 'Registrace byla zrušena kvůli neuhrazené platbě.');
    }
}
