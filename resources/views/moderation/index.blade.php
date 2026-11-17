@extends('layouts.app')

@section('title', 'Moderace')

@section('content')
    <h1 class="h4 mb-3">Moderace</h1>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Události ke schválení</p>
                    <p class="h3 mb-2">{{ $pendingEvents }}</p>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('moderation.events') }}">Otevřít frontu</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Místa ke schválení</p>
                    <p class="h3 mb-2">{{ $pendingVenues }}</p>
                    <a class="btn btn-sm btn-outline-primary"
                       href="{{ route('moderation.venues', ['stav' => 'pending']) }}">Otevřít frontu</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Kategorie ke schválení</p>
                    <p class="h3 mb-2">{{ $pendingCategories }}</p>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('moderation.categories') }}">Otevřít správu</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Skryté komentáře</p>
                    <p class="h3 mb-2">{{ $hiddenComments }}</p>
                    <a class="btn btn-sm btn-outline-primary"
                       href="{{ route('moderation.comments', ['stav' => 'hidden']) }}">Otevřít moderaci</a>
                </div>
            </div>
        </div>
    </div>
@endsection
