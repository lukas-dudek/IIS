@extends('layouts.app')

@section('title', 'Události')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Události</h1>
        @auth
            <a class="btn btn-primary" href="{{ route('events.create') }}">Založit událost</a>
        @endauth
    </div>

    <form method="get" action="{{ route('events.index') }}" class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="q" class="form-label">Hledaný výraz</label>
                <input type="search" class="form-control" id="q" name="q" value="{{ request('q') }}"
                       placeholder="Název, popis, místo">
            </div>
            <div class="col-md-4">
                <label for="kategorie" class="form-label">Kategorie</label>
                <select class="form-select" id="kategorie" name="kategorie">
                    <option value="">Všechny kategorie</option>
                    @include('partials.category-options', ['nodes' => $tree, 'depth' => 1, 'selected' => (int) request('kategorie')])
                </select>
            </div>
            <div class="col-md-4">
                <label for="mesto" class="form-label">Město</label>
                <select class="form-select" id="mesto" name="mesto">
                    <option value="">Všechna města</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}" @selected(request('mesto') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="od" class="form-label">Od</label>
                <input type="date" class="form-control" id="od" name="od" value="{{ request('od') }}">
            </div>
            <div class="col-md-3">
                <label for="do" class="form-label">Do</label>
                <input type="date" class="form-control" id="do" name="do" value="{{ request('do') }}">
            </div>
            <div class="col-md-3">
                <label for="vstupne" class="form-label">Vstupné</label>
                <select class="form-select" id="vstupne" name="vstupne">
                    <option value="">Vše</option>
                    <option value="free" @selected(request('vstupne') === 'free')>Zdarma</option>
                    <option value="paid" @selected(request('vstupne') === 'paid')>Placené</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="dostupnost" class="form-label">Dostupnost</label>
                <select class="form-select" id="dostupnost" name="dostupnost">
                    <option value="">Vše</option>
                    <option value="available" @selected(request('dostupnost') === 'available')>S volnou kapacitou</option>
                    <option value="full" @selected(request('dostupnost') === 'full')>Obsazené</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="razeni" class="form-label">Řazení</label>
                <select class="form-select" id="razeni" name="razeni">
                    <option value="date" @selected($sort === 'date')>Podle data</option>
                    <option value="price" @selected($sort === 'price')>Podle ceny</option>
                    <option value="title" @selected($sort === 'title')>Podle názvu</option>
                </select>
            </div>
            <div class="col-md-8 d-flex align-items-end gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="archiv" name="archiv" value="1"
                           @checked(request('archiv') === '1')>
                    <label class="form-check-label" for="archiv">Zahrnout proběhlé události</label>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrovat</button>
            <a class="btn btn-outline-secondary" href="{{ route('events.index') }}">Zrušit filtry</a>
        </div>
    </form>

    <p class="text-muted small">
        Nalezeno {{ $events->total() }} {{ czech_plural($events->total(), 'událost', 'události', 'událostí') }}.
    </p>

    @if($events->isEmpty())
        <div class="alert alert-info">
            Podle zadaných filtrů jsme nic nenašli. Zkuste zrušit filtry nebo
            <a href="{{ route('events.index') }}">zobrazit všechny události</a>.
        </div>
    @else
        <div class="row g-3">
            @foreach($events as $event)
                <div class="col-md-6 col-xl-4">
                    @include('partials.event-card', ['event' => $event])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
@endsection
