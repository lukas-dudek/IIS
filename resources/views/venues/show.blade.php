@extends('layouts.app')

@section('title', $venue->name)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('venues.index') }}">Místa</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $venue->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h1 class="h4">{{ $venue->name }}</h1>
                    <p class="mb-1">{{ $venue->street }}</p>
                    <p class="mb-3">{{ $venue->postal_code }} {{ $venue->city }}</p>
                    @if($venue->description)
                        <p class="mb-0" style="white-space: pre-line">{{ $venue->description }}</p>
                    @endif
                </div>
            </div>

            @if($venue->photo_path)
                <div class="card mt-3">
                    <img src="{{ Storage::disk('public')->url($venue->photo_path) }}" alt="Fotografie místa"
                         class="card-img-top">
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Připravené události</div>
                @if($events->isEmpty())
                    <div class="card-body text-muted">
                        V tomto místě zatím nejsou vypsané žádné události.
                        <a href="{{ route('events.index') }}">Prohlédněte ostatní události</a>.
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($events as $event)
                            <li class="list-group-item">
                                <a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a>
                                <div class="small text-muted">
                                    {{ format_date_range($event->starts_at, $event->ends_at) }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
