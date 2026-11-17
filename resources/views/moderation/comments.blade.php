@extends('layouts.app')

@section('title', 'Moderace komentářů')

@section('content')
    @include('moderation.nav')

    <h1 class="h4 mb-3">Komentáře</h1>

    <form method="get" action="{{ route('moderation.comments') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div>
            <label for="stav" class="form-label small mb-1">Stav</label>
            <select class="form-select form-select-sm" id="stav" name="stav">
                <option value="" @selected($status === '')>Všechny</option>
                <option value="visible" @selected($status === 'visible')>Zobrazené</option>
                <option value="hidden" @selected($status === 'hidden')>Skryté</option>
            </select>
        </div>
        <div>
            <label for="udalost" class="form-label small mb-1">Událost</label>
            <select class="form-select form-select-sm" id="udalost" name="udalost">
                <option value="0">Všechny události</option>
                @foreach($events as $event)
                    <option value="{{ $event->id }}" @selected($eventId === $event->id)>{{ $event->title }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
    </form>

    @if($comments->isEmpty())
        <div class="alert alert-info">Žádné komentáře pro zadaný filtr.</div>
    @else
        <div class="d-flex flex-column gap-3">
            @foreach($comments as $comment)
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <a href="{{ route('events.show', $comment->event->slug) }}">{{ $comment->event->title }}</a>
                                <div class="small text-muted">
                                    {{ $comment->author->name }} · {{ format_datetime($comment->created_at) }}
                                </div>
                            </div>
                            <span class="badge {{ status_badge_class($comment->status) }}">
                                {{ comment_status_label($comment->status) }}
                            </span>
                        </div>
                        <p class="mb-2" style="white-space: pre-line">{{ $comment->body }}</p>
                        @if($comment->hidden_reason)
                            <p class="small text-danger mb-2">
                                Skryto {{ format_datetime($comment->moderated_at) }}
                                ({{ $comment->moderator?->name }}): {{ $comment->hidden_reason }}
                            </p>
                        @endif
                        @if($comment->isVisible())
                            <form method="post" action="{{ route('moderation.comments.hide', $comment) }}"
                                  class="d-flex flex-wrap gap-2 align-items-start">
                                @csrf
                                <input type="text" class="form-control form-control-sm" style="max-width: 22rem"
                                       name="reason" placeholder="Důvod skrytí (3–255 znaků)" minlength="3" maxlength="255"
                                       required aria-label="Důvod skrytí">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Skrýt komentář</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('moderation.comments.show', $comment) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Znovu zobrazit</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $comments->links() }}</div>
    @endif
@endsection
