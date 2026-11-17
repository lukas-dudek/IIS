@extends('layouts.app')

@section('title', 'Místa konání')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Místa konání</h1>
        @auth
            <a class="btn btn-outline-primary" href="{{ route('venues.propose') }}">Navrhnout nové místo</a>
        @endauth
    </div>

    <form method="get" action="{{ route('venues.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-4">
        <div>
            <label for="mesto" class="form-label small mb-1">Město</label>
            <select class="form-select form-select-sm" id="mesto" name="mesto">
                <option value="">Všechna města</option>
                @foreach($cities as $option)
                    <option value="{{ $option }}" @selected($city === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
        @if($city !== '')
            <a class="btn btn-sm btn-link" href="{{ route('venues.index') }}">Zrušit filtr</a>
        @endif
    </form>

    @if($venues->isEmpty())
        <div class="alert alert-info">
            Katalog míst je prázdný.
            @auth
                <a href="{{ route('venues.propose') }}">Navrhněte první místo</a>.
            @endauth
        </div>
    @else
        <div class="row g-3">
            @foreach($venues as $venue)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="h6">
                                <a class="stretched-link text-decoration-none"
                                   href="{{ route('venues.show', $venue) }}">{{ $venue->name }}</a>
                            </h2>
                            <p class="small text-muted mb-2">
                                {{ $venue->street }}<br>
                                {{ $venue->postal_code }} {{ $venue->city }}
                            </p>
                            @if($venue->description)
                                <p class="small mb-2">{{ \Illuminate\Support\Str::limit($venue->description, 120) }}</p>
                            @endif
                            <p class="small mb-0 text-muted">
                                {{ $venue->events_count }}
                                {{ czech_plural($venue->events_count, 'připravená akce', 'připravené akce', 'připravených akcí') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $venues->links() }}</div>
    @endif
@endsection
