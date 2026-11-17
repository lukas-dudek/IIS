@extends('layouts.app')

@section('title', $event->title)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('events.index') }}">Události</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $event->title }}</li>
        </ol>
    </nav>

    @if($event->status !== 'approved')
        <div class="alert alert-warning">
            <strong>{{ event_status_label($event->status) }}</strong>
            @if($event->rejection_reason)
                <div>Důvod: {{ $event->rejection_reason }}</div>
            @endif
            @if($event->cancelled_reason)
                <div>Důvod zrušení: {{ $event->cancelled_reason }}</div>
            @endif
            <div class="small mt-1">Událost není veřejně viditelná, dokud ji moderátor neschválí.</div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <h1 class="h3 mb-0">{{ $event->title }}</h1>
                        <span class="badge {{ status_badge_class($event->status) }}">{{ event_status_label($event->status) }}</span>
                    </div>
                    <p class="text-muted">
                        <a href="{{ route('venues.show', $event->venue) }}">{{ $event->venue->name }}</a>,
                        {{ $event->venue->street }}, {{ $event->venue->city }}
                    </p>
                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Termín</dt>
                        <dd class="col-sm-8">{{ format_date_range($event->starts_at, $event->ends_at) }}</dd>
                        <dt class="col-sm-4">Kategorie</dt>
                        <dd class="col-sm-8">{{ $event->category->path() }}</dd>
                        <dt class="col-sm-4">Správce</dt>
                        <dd class="col-sm-8">{{ $event->manager->name }}</dd>
                        <dt class="col-sm-4">Kapacita</dt>
                        <dd class="col-sm-8">
                            {{ $event->capacity === null ? 'Neomezená' : $event->capacity }}
                            (aktivní registrace: {{ $activeCount }})
                        </dd>
                        @if($ratingAverage !== null)
                            <dt class="col-sm-4">Hodnocení</dt>
                            <dd class="col-sm-8">
                                {{ number_format($ratingAverage, 1, ',', ' ') }} / 5
                                ({{ $event->ratings->count() }}
                                {{ czech_plural($event->ratings->count(), 'hodnocení', 'hodnocení', 'hodnocení') }})
                            </dd>
                        @endif
                    </dl>
                    @if($event->cover_path)
                        <img src="{{ Storage::disk('public')->url($event->cover_path) }}" alt="Fotografie události"
                             class="img-fluid rounded border mb-3">
                    @endif
                    <div class="mb-0" style="white-space: pre-line">{{ $event->description }}</div>
                </div>
            </div>

            @if($event->status === 'approved' || $registration !== null)
                <div class="card mt-4">
                    <div class="card-header">Vstupenky</div>
                    <div class="card-body">
                        @error('registration')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                        @error('ticket_type_id')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        @if($registration !== null)
                            <p class="mb-1">
                                Vaše registrace:
                                <span class="badge {{ status_badge_class($registration->status) }}">
                                    {{ registration_status_label($registration->status) }}
                                </span>
                            </p>
                            <p class="small mb-2">
                                Typ: {{ $registration->ticketType->name }} · cena {{ format_money($registration->price) }}
                                @if($registration->variable_symbol)
                                    · variabilní symbol <strong>{{ $registration->variable_symbol }}</strong>
                                @endif
                            </p>
                            @if($registration->isPending() && $event->payment_note)
                                <p class="small text-muted">{{ $event->payment_note }}</p>
                            @endif
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('registrations.index') }}">Správovat registrace</a>
                        @elseif($event->hasStarted())
                            <p class="mb-0 text-muted">Registrace už není možná, událost začala.</p>
                        @else
                            <form method="post" action="{{ route('registrations.store', $event) }}">
                                @csrf
                                @foreach($event->ticketTypes as $type)
                                    @php
                                        $typeActive = $type->registrations_count
                                            ?? $type->registrations()->whereIn('status', ['pending', 'confirmed'])->count();
                                    @endphp
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="ticket_type_id"
                                               id="ticket-{{ $type->id }}" value="{{ $type->id }}"
                                               @checked(old('ticket_type_id') == $type->id)
                                               @disabled($type->capacity !== null && $typeActive >= $type->capacity)>
                                        <label class="form-check-label" for="ticket-{{ $type->id }}">
                                            {{ $type->name }} —
                                            {{ (float) $type->price > 0 ? format_money($type->price) : 'zdarma' }}
                                            @if($type->capacity !== null)
                                                <span class="text-muted small">
                                                    ({{ $typeActive }} / {{ $type->capacity }} obsazeno)
                                                </span>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                                <button type="submit" class="btn btn-primary mt-2">Registrovat se</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card mt-4">
                <div class="card-header">Komentáře ({{ $event->comments->count() }})</div>
                <div class="card-body">
                    @if($canFeedback || $myComment !== null)
                        <form method="post" action="{{ route('comments.store', $event) }}" class="mb-4">
                            @csrf
                            <label for="body" class="form-label">
                                Váš komentář
                                <span class="text-muted">(nepovinné, 1–2000 znaků)</span>
                            </label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body"
                                      rows="3" maxlength="2000">{{ old('body', $myComment?->body ?? '') }}</textarea>
                            @error('body')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn btn-secondary btn-sm mt-2">Uložit komentář</button>
                        </form>
                    @endif

                    @forelse($event->comments as $comment)
                        <div class="border-bottom pb-3 mb-3">
                            <p class="mb-1 small text-muted">
                                {{ $comment->author->name }} · {{ format_datetime($comment->created_at) }}
                            </p>
                            <p class="mb-1" style="white-space: pre-line">{{ $comment->body }}</p>
                            @auth
                                @if($comment->user_id === auth()->id())
                                    <div class="d-flex gap-2">
                                        <form method="post" action="{{ route('comments.destroy', $comment) }}"
                                              onsubmit="return confirm('Opravdu smazat komentář?');">
                                            @csrf
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-danger">Smazat komentář</button>
                                        </form>
                                    </div>
                                @endif
                            @endauth
                        </div>
                    @empty
                        <p class="mb-0 text-muted">Zatím žádné komentáře.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Akce</div>
                <div class="card-body d-flex flex-column gap-2">
                    @auth
                        @can('manage', $event)
                            @if(!$event->isCancelled())
                                <a class="btn btn-outline-primary" href="{{ route('events.edit', $event) }}">Upravit událost</a>
                            @endif
                            <a class="btn btn-outline-secondary" href="{{ route('registrations.participants', $event) }}">
                                Účastníci a platby
                            </a>
                            @can('submit', $event)
                                <form method="post" action="{{ route('events.submit', $event) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100">Odeslat ke schválení</button>
                                </form>
                            @endcan
                            @can('cancel', $event)
                                <form method="post" action="{{ route('events.cancel', $event) }}"
                                      onsubmit="return confirm('Zrušením události se zruší i aktivní registrace. Pokračovat?');">
                                    @csrf
                                    <label for="reason" class="form-label small mt-2">Důvod zrušení <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="reason" name="reason"
                                           minlength="3" maxlength="255" required>
                                    <button type="submit" class="btn btn-outline-danger w-100 mt-2">Zrušit událost</button>
                                </form>
                            @endcan
                        @endcan
                    @endauth

                    @if($event->isApproved() && $event->isFinished() && $canFeedback)
                        <hr>
                        <form method="post" action="{{ route('ratings.store', $event) }}">
                            @csrf
                            <label for="value" class="form-label">Hodnocení <span class="text-danger">*</span></label>
                            <select class="form-select" id="value" name="value" required>
                                @for($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected((int) old('value', $myRating?->value) === $i)>
                                        {{ $i }} — {{ $i === 1 ? 'velmi špatné' : ($i === 5 ? 'výborné' : '') }}
                                    </option>
                                @endfor
                            </select>
                            <button type="submit" class="btn btn-secondary w-100 mt-2">Uložit hodnocení</button>
                        </form>
                    @elseif($myRating !== null)
                        <p class="small text-muted mb-0">Vaše hodnocení: {{ $myRating->value }} / 5</p>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Místo konání</div>
                <div class="card-body small">
                    <p class="mb-1 fw-semibold">{{ $event->venue->name }}</p>
                    <p class="mb-1">{{ $event->venue->street }}</p>
                    <p class="mb-2">{{ $event->venue->postal_code }} {{ $event->venue->city }}</p>
                    @if($event->venue->description)
                        <p class="text-muted mb-2">{{ $event->venue->description }}</p>
                    @endif
                    <a href="{{ route('venues.show', $event->venue) }}">Detail místa</a>
                </div>
            </div>
        </div>
    </div>
@endsection
