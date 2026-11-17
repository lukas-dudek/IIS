@extends('layouts.app')

@section('title', 'Domů')

@section('content')
    <div class="p-4 p-md-5 mb-4 bg-white border rounded-3">
        <h1 class="h3">Události v Brně a okolí</h1>
        <p class="lead mb-3">
            Procházejte schválené akce, registrujte se na ně a spravujte své vlastní události.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('events.index') }}">Zobrazit události</a>
            @guest
                <a class="btn btn-outline-secondary" href="{{ route('register') }}">Vytvořit účet</a>
            @endguest
            @auth
                <a class="btn btn-outline-secondary" href="{{ route('events.create') }}">Založit událost</a>
            @endauth
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Připravené události</p>
                    <p class="h4 mb-0">{{ $eventCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Schválená místa</p>
                    <p class="h4 mb-0">{{ $venueCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Zveřejněné komentáře</p>
                    <p class="h4 mb-0">{{ $commentCount }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">Nejbližší události</h2>
        <a class="link-secondary small" href="{{ route('events.index') }}">Všechny události</a>
    </div>

    @if($events->isEmpty())
        <div class="alert alert-info">
            Zatím nejsou vypsané žádné schválené události.
            @auth
                <a href="{{ route('events.create') }}">Založte první událost</a> a odešlete ji ke schválení.
            @else
                <a href="{{ route('register') }}">Zaregistrujte se</a> a založte první událost.
            @endauth
        </div>
    @else
        <div class="row g-3">
            @foreach($events as $event)
                <div class="col-md-6 col-xl-4">
                    @include('partials.event-card', ['event' => $event])
                </div>
            @endforeach
        </div>
    @endif
@endsection
