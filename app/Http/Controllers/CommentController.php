<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Event;
use App\Services\FeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommentController extends Controller
{
    public function __construct(private FeedbackService $feedback)
    {
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ], [], ['body' => 'komentář']);

        $this->feedback->saveComment($event, $request->user(), $validated['body']);

        return redirect()
            ->route('events.show', $event->slug)
            ->with('status', 'Komentář byl uložen.');
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ], [], ['body' => 'komentář']);

        $this->feedback->updateComment($comment, $request->user(), $validated['body']);

        return redirect()
            ->route('events.show', $comment->event->slug)
            ->with('status', 'Komentář byl upraven.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $event = $comment->event;
        $this->feedback->deleteComment($comment, $request->user());

        return redirect()
            ->route('events.show', $event->slug)
            ->with('status', 'Komentář byl smazán.');
    }
}
