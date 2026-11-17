<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RatingController extends Controller
{
    public function __construct(private FeedbackService $feedback)
    {
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'integer', 'min:1', 'max:5'],
        ], [], ['value' => 'hodnocení']);

        $this->feedback->saveRating($event, $request->user(), (int) $validated['value']);

        return redirect()
            ->route('events.show', $event->slug)
            ->with('status', 'Hodnocení bylo uloženo.');
    }
}
