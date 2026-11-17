@extends('layouts.app')

@section('title', 'Moje registrace')

@section('content')
    <h1 class="h4 mb-3">Moje registrace</h1>

    @if($registrations->isEmpty())
        <div class="alert alert-info">
            Nemáte žádnou registraci. <a href="{{ route('events.index') }}">Vyberte si událost</a>.
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-white">
                <thead>
                <tr>
                    <th scope="col">Událost</th>
                    <th scope="col">Termín</th>
                    <th scope="col">Vstupenka</th>
                    <th scope="col">Cena</th>
                    <th scope="col">Stav</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($registrations as $registration)
                    <tr>
                        <td>
                            <a href="{{ route('events.show', $registration->event->slug) }}">
                                {{ $registration->event->title }}
                            </a>
                            <div class="small text-muted">{{ $registration->event->venue->name }}</div>
                        </td>
                        <td class="small">
                            {{ format_date_range($registration->event->starts_at, $registration->event->ends_at) }}
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
                                <div class="small text-muted">Úhrada oznámena</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($registration->isActive() && !$registration->event->hasStarted())
                                <div class="d-flex flex-column gap-1 align-items-stretch">
                                    @if($registration->isPending() && $registration->payment_claimed_at === null)
                                        <form method="post" action="{{ route('registrations.claim-payment', $registration) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Uhradil jsem</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('registrations.cancel', $registration) }}"
                                          onsubmit="return confirm('Opravdu zrušit registraci?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">Zrušit registraci</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $registrations->links() }}</div>
    @endif
@endsection
