@extends('layouts.app')

@section('title', 'Účastníci události')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('events.mine') }}">Moje události</a></li>
            <li class="breadcrumb-item active" aria-current="page">Účastníci</li>
        </ol>
    </nav>

    <h1 class="h4 mb-1">{{ $event->title }}</h1>
    <p class="text-muted">
        {{ format_date_range($event->starts_at, $event->ends_at) }} ·
        {{ $event->venue->name }}
    </p>

    @if($event->payment_note)
        <div class="alert alert-info small">
            <span class="fw-semibold">Platební instrukce:</span> {{ $event->payment_note }}
        </div>
    @endif

    @if($registrations->isEmpty())
        <div class="alert alert-info">
            Zatím se nikdo neregistroval. Po registraci se účastníci objeví zde.
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-white">
                <thead>
                <tr>
                    <th scope="col">Účastník</th>
                    <th scope="col">Vstupenka</th>
                    <th scope="col">Cena</th>
                    <th scope="col">Stav</th>
                    <th scope="col">Registrace</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($registrations as $registration)
                    <tr>
                        <td>
                            {{ $registration->user->name }}
                            <div class="small text-muted">{{ $registration->user->email }}</div>
                        </td>
                        <td class="small">{{ $registration->ticketType->name }}</td>
                        <td class="small">
                            {{ format_money($registration->price) }}
                            @if($registration->variable_symbol)
                                <div class="text-muted">VS {{ $registration->variable_symbol }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ status_badge_class($registration->status) }}">
                                {{ registration_status_label($registration->status) }}
                            </span>
                            @if($registration->payment_claimed_at)
                                <div class="small text-muted">
                                    Úhrada oznámena {{ format_datetime($registration->payment_claimed_at) }}
                                </div>
                            @endif
                        </td>
                        <td class="small">{{ format_datetime($registration->registered_at) }}</td>
                        <td class="text-end">
                            @if($registration->isPending())
                                <div class="d-flex gap-1 justify-content-end">
                                    <form method="post" action="{{ route('registrations.confirm-payment', $registration) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Potvrdit platbu</button>
                                    </form>
                                    <form method="post" action="{{ route('registrations.reject-payment', $registration) }}"
                                          onsubmit="return confirm('Odmítnutím se registrace zruší. Pokračovat?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Odmítnout</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
