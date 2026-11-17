@extends('layouts.app')

@section('title', 'Posouzení události')

@section('content')
    @include('moderation.nav')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('moderation.events') }}">Události ke schválení</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $event->title }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h1 class="h4">{{ $event->title }}</h1>
                        <span class="badge {{ status_badge_class($event->status) }}">
                            {{ event_status_label($event->status) }}
                        </span>
                    </div>
                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Správce</dt>
                        <dd class="col-sm-8">{{ $event->manager->name }} ({{ $event->manager->username }})</dd>
                        <dt class="col-sm-4">Termín</dt>
                        <dd class="col-sm-8">{{ format_date_range($event->starts_at, $event->ends_at) }}</dd>
                        <dt class="col-sm-4">Místo</dt>
                        <dd class="col-sm-8">{{ $event->venue->fullAddress() }}</dd>
                        <dt class="col-sm-4">Kategorie</dt>
                        <dd class="col-sm-8">{{ $event->category->path() }}</dd>
                        <dt class="col-sm-4">Kapacita</dt>
                        <dd class="col-sm-8">
                            {{ $event->capacity === null ? 'Neomezená' : $event->capacity }}
                            · aktivní registrace: {{ $activeCount }}
                        </dd>
                        <dt class="col-sm-4">Odesláno</dt>
                        <dd class="col-sm-8">{{ format_datetime($event->submitted_at) }}</dd>
                    </dl>
                    <p class="mb-0" style="white-space: pre-line">{{ $event->description }}</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Typy vstupenek</div>
                <ul class="list-group list-group-flush">
                    @forelse($event->ticketTypes as $type)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $type->name }}</span>
                            <span>
                                {{ format_money($type->price) }}
                                @if($type->capacity !== null)
                                    <span class="text-muted small">· kapacita {{ $type->capacity }}</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Bez typů vstupenek.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            @if($event->status === 'pending')
                <div class="card">
                    <div class="card-header">Rozhodnutí</div>
                    <div class="card-body d-flex flex-column gap-3">
                        <form method="post" action="{{ route('moderation.events.approve', $event) }}"
                              onsubmit="return confirm('Opravdu schválit událost?');">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">Schválit událost</button>
                        </form>
                        <form method="post" action="{{ route('moderation.events.reject', $event) }}">
                            @csrf
                            <label for="reason" class="form-label">Důvod zamítnutí <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" minlength="3"
                                      maxlength="255" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100 mt-2">Zamítnout událost</button>
                        </form>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary">
                    Událost už není ve frontě. Aktuální stav: {{ event_status_label($event->status) }}.
                </div>
            @endif

            <div class="card mt-3">
                <div class="card-body">
                    <a class="btn btn-link px-0" href="{{ route('events.show', $event->slug) }}">
                        Zobrazit veřejný detail události
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
