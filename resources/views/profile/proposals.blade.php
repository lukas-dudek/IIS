@extends('layouts.app')

@section('title', 'Moje návrhy')

@section('content')
    <h1 class="h4 mb-3">Moje návrhy</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Místa konání</span>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('venues.propose') }}">Navrhnout místo</a>
                </div>
                @if($venues->isEmpty())
                    <div class="card-body text-muted">
                        Zatím jste nenavrhli žádné místo. Chybí vám místo, kde se akce koná?
                        <a href="{{ route('venues.propose') }}">Navrhněte ho</a>.
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($venues as $venue)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <a href="{{ route('venues.show', $venue) }}">{{ $venue->name }}</a>
                                    <div class="small text-muted">
                                        {{ $venue->street }}, {{ $venue->city }}
                                    </div>
                                    @if($venue->rejection_reason)
                                        <div class="small text-danger">{{ $venue->rejection_reason }}</div>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ status_badge_class($venue->status) }}">
                                        {{ catalog_status_label($venue->status) }}
                                    </span>
                                    @if(in_array($venue->status, ['pending', 'rejected'], true))
                                        <div class="mt-1">
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="{{ route('venues.edit-proposal', $venue) }}">Upravit</a>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Kategorie</span>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('categories.propose') }}">Navrhnout kategorii</a>
                </div>
                @if($categories->isEmpty())
                    <div class="card-body text-muted">
                        Zatím jste nenavrhli žádnou kategorii. Chybí vám vhodná kategorie?
                        <a href="{{ route('categories.propose') }}">Navrhněte ji</a>.
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($categories as $category)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <span>{{ $category->name }}</span>
                                    <div class="small text-muted">{{ $category->path() }}</div>
                                    @if($category->rejection_reason)
                                        <div class="small text-danger">{{ $category->rejection_reason }}</div>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ status_badge_class($category->status) }}">
                                        {{ catalog_status_label($category->status) }}
                                    </span>
                                    @if(in_array($category->status, ['pending', 'rejected'], true))
                                        <div class="mt-1">
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="{{ route('categories.edit-proposal', $category) }}">Upravit</a>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
