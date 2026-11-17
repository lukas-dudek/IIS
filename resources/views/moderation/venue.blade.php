@extends('layouts.app')

@section('title', 'Místo: ' . $venue->name)

@section('content')
    @include('moderation.nav')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('moderation.venues') }}">Místa</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $venue->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h1 class="h4 mb-0">{{ $venue->name }}</h1>
                        <span class="badge {{ status_badge_class($venue->status) }}">
                            {{ catalog_status_label($venue->status) }}
                        </span>
                    </div>
                    <p class="mb-1">{{ $venue->street }}</p>
                    <p class="mb-2">{{ $venue->postal_code }} {{ $venue->city }}</p>
                    @if($venue->description)
                        <p class="mb-2" style="white-space: pre-line">{{ $venue->description }}</p>
                    @endif
                    @if($venue->photo_path)
                        <img src="{{ Storage::disk('public')->url($venue->photo_path) }}" alt="Fotografie místa"
                             class="img-fluid rounded border" style="max-height: 320px">
                    @endif
                    <dl class="row small mt-3 mb-0">
                        <dt class="col-sm-4">Navrhl</dt>
                        <dd class="col-sm-8">{{ $venue->proposer->name }}</dd>
                        @if($venue->reviewer !== null)
                            <dt class="col-sm-4">Posoudil</dt>
                            <dd class="col-sm-8">
                                {{ $venue->reviewer->name }}, {{ format_datetime($venue->reviewed_at) }}
                            </dd>
                        @endif
                        @if($venue->rejection_reason)
                            <dt class="col-sm-4">Důvod zamítnutí</dt>
                            <dd class="col-sm-8 text-danger">{{ $venue->rejection_reason }}</dd>
                        @endif
                        <dt class="col-sm-4">Události</dt>
                        <dd class="col-sm-8">{{ $venue->events()->count() }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Správa</div>
                <div class="card-body d-flex flex-column gap-2">
                    @if($venue->isPending())
                        <form method="post" action="{{ route('moderation.venues.approve', $venue) }}"
                              onsubmit="return confirm('Schválit místo?');">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">Schválit místo</button>
                        </form>
                        <form method="post" action="{{ route('moderation.venues.reject', $venue) }}">
                            @csrf
                            <label for="reason" class="form-label small">Důvod zamítnutí <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="reason" name="reason" rows="2"
                                      minlength="3" maxlength="255" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100 mt-2">Zamítnout místo</button>
                        </form>
                    @endif
                    <a class="btn btn-outline-secondary" href="{{ route('moderation.venues.edit', $venue) }}">
                        Upravit údaje
                    </a>
                    <form method="post" action="{{ route('moderation.venues.delete', $venue) }}"
                          onsubmit="return confirm('Opravdu smazat místo? Mazání je možné jen bez navázaných událostí.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Smazat místo</button>
                    </form>
                    @if($venue->isApproved())
                        <a class="btn btn-link px-0" href="{{ route('venues.show', $venue) }}">Veřejný detail</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
