@extends('layouts.app')

@section('title', 'Moderace míst')

@section('content')
    @include('moderation.nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Místa konání</h1>
        <a class="btn btn-primary" href="{{ route('moderation.venues.create') }}">Vytvořit schválené místo</a>
    </div>

    <form method="get" action="{{ route('moderation.venues') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div>
            <label for="stav" class="form-label small mb-1">Stav</label>
            <select class="form-select form-select-sm" id="stav" name="stav">
                <option value="pending" @selected($status === 'pending')>Čeká na schválení</option>
                <option value="approved" @selected($status === 'approved')>Schválená</option>
                <option value="rejected" @selected($status === 'rejected')>Zamítnutá</option>
                <option value="" @selected($status === '')>Všechna</option>
            </select>
        </div>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrovat</button>
    </form>

    @if($venues->isEmpty())
        <div class="alert alert-info">Žádná místa v této kategorii filtru.</div>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-white">
                <thead>
                <tr>
                    <th scope="col">Místo</th>
                    <th scope="col">Adresa</th>
                    <th scope="col">Navrhl</th>
                    <th scope="col">Stav</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($venues as $venue)
                    <tr>
                        <td>
                            <a href="{{ route('moderation.venue', $venue) }}">{{ $venue->name }}</a>
                            @if($venue->rejection_reason)
                                <div class="small text-danger">{{ $venue->rejection_reason }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $venue->street }}, {{ $venue->postal_code }} {{ $venue->city }}</td>
                        <td class="small">{{ $venue->proposer->name }}</td>
                        <td>
                            <span class="badge {{ status_badge_class($venue->status) }}">
                                {{ catalog_status_label($venue->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('moderation.venue', $venue) }}">
                                Detail
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $venues->links() }}</div>
    @endif
@endsection
