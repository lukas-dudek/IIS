@extends('layouts.app')

@section('title', 'Moderace kategorií')

@section('content')
    @include('moderation.nav')

    <h1 class="h4 mb-3">Kategorie</h1>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Strom kategorií</span>
                    <a class="btn btn-sm btn-primary" href="{{ route('moderation.categories.create') }}">
                        Vytvořit schválenou kategorii
                    </a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse($rows as $row)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div style="padding-left: {{ ($row['depth'] - 1) * 1.5 }}rem">
                                <a href="{{ route('moderation.category', $row['model']) }}">{{ $row['model']->name }}</a>
                                <span class="badge {{ status_badge_class($row['model']->status) }} ms-1">
                                    {{ catalog_status_label($row['model']->status) }}
                                </span>
                                <div class="small text-muted">
                                    úroveň {{ $row['depth'] }} · slug {{ $row['model']->slug }}
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('moderation.categories.edit', $row['model']) }}">Upravit</a>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item">Zatím nejsou žádné kategorie.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Návrhy ke schválení</div>
                @if($pending->isEmpty())
                    <div class="card-body text-muted">Žádné návrhy nečekají na schválení.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($pending as $category)
                            <li class="list-group-item">
                                <a href="{{ route('moderation.category', $category) }}">{{ $category->name }}</a>
                                <div class="small text-muted">
                                    navrhl {{ $category->proposer->name }},
                                    {{ $category->parent === null ? 'kořen' : 'pod ' . $category->parent->name }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
