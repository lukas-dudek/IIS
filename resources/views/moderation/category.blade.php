@extends('layouts.app')

@section('title', 'Kategorie: ' . $category->name)

@section('content')
    @include('moderation.nav')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('moderation.categories') }}">Kategorie</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h1 class="h4 mb-0">{{ $category->name }}</h1>
                        <span class="badge {{ status_badge_class($category->status) }}">
                            {{ catalog_status_label($category->status) }}
                        </span>
                    </div>
                    <p class="small text-muted mb-2">{{ $category->path() }}</p>
                    @if($category->description)
                        <p class="mb-2" style="white-space: pre-line">{{ $category->description }}</p>
                    @endif
                    <dl class="row small mb-0">
                        <dt class="col-sm-4">Rodič</dt>
                        <dd class="col-sm-8">{{ $category->parent?->name ?? 'kořen stromu' }}</dd>
                        <dt class="col-sm-4">Navrhl</dt>
                        <dd class="col-sm-8">{{ $category->proposer->name }}</dd>
                        @if($category->reviewer !== null)
                            <dt class="col-sm-4">Posoudil</dt>
                            <dd class="col-sm-8">
                                {{ $category->reviewer->name }}, {{ format_datetime($category->reviewed_at) }}
                            </dd>
                        @endif
                        @if($category->rejection_reason)
                            <dt class="col-sm-4">Důvod zamítnutí</dt>
                            <dd class="col-sm-8 text-danger">{{ $category->rejection_reason }}</dd>
                        @endif
                        <dt class="col-sm-4">Podkategorie</dt>
                        <dd class="col-sm-8">{{ $children->count() }}</dd>
                        <dt class="col-sm-4">Události</dt>
                        <dd class="col-sm-8">{{ $category->events()->count() }}</dd>
                    </dl>
                </div>
            </div>

            @if($children->isNotEmpty())
                <div class="card mt-3">
                    <div class="card-header">Podkategorie</div>
                    <ul class="list-group list-group-flush">
                        @foreach($children as $child)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('moderation.category', $child) }}">{{ $child->name }}</a>
                                    <span class="badge {{ status_badge_class($child->status) }} ms-1">
                                        {{ catalog_status_label($child->status) }}
                                    </span>
                                </div>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('moderation.categories.edit', $child) }}">Upravit</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Správa</div>
                <div class="card-body d-flex flex-column gap-2">
                    @if($category->isPending())
                        <form method="post" action="{{ route('moderation.categories.approve', $category) }}"
                              onsubmit="return confirm('Schválit kategorii?');">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">Schválit kategorii</button>
                        </form>
                        <form method="post" action="{{ route('moderation.categories.reject', $category) }}">
                            @csrf
                            <label for="reason" class="form-label small">Důvod zamítnutí <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-sm" id="reason" name="reason" rows="2"
                                      minlength="3" maxlength="255" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100 mt-2">Zamítnout kategorii</button>
                        </form>
                    @endif
                    <a class="btn btn-outline-secondary" href="{{ route('moderation.categories.edit', $category) }}">
                        Upravit kategorii
                    </a>
                    <form method="post" action="{{ route('moderation.categories.delete', $category) }}"
                          onsubmit="return confirm('Opravdu smazat kategorii? Mazání je možné jen bez podkategorií a událostí.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">Smazat kategorii</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
