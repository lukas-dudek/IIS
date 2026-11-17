@extends('layouts.app')

@section('title', 'Můj kalendář')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Můj kalendář</h1>
        <a class="btn btn-outline-secondary" href="{{ route('calendar.export') }}">Exportovat do .ics</a>
    </div>

    <div class="d-flex align-items-center gap-2 mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['mesic' => $previousMonth]) }}"
           aria-label="Předchozí měsíc">‹</a>
        <h2 class="h5 mb-0 text-capitalize">{{ $monthLabel }}</h2>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['mesic' => $nextMonth]) }}"
           aria-label="Následující měsíc">›</a>
        @if(!$isCurrentMonth)
            <a class="btn btn-sm btn-link" href="{{ route('calendar.index') }}">Aktuální měsíc</a>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-bordered bg-white align-top">
            <thead>
            <tr>
                @foreach(['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'] as $weekday)
                    <th scope="col" class="text-center small">{{ $weekday }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($weeks as $week)
                <tr>
                    @foreach($week as $day)
                        @if($day === null)
                            <td class="bg-light" style="height: 7rem"></td>
                        @else
                            <td @class(['table-primary' => $isCurrentMonth && $day === $today])
                                style="height: 7rem; width: 14.28%">
                                <div class="small fw-semibold mb-1">{{ $day }}</div>
                                @foreach($eventsByDay[$day] ?? [] as $event)
                                    <div class="small mb-1">
                                        <a class="text-decoration-none" href="{{ route('events.show', $event->slug) }}">
                                            {{ $event->starts_at->setTimezone('Europe/Prague')->format('H:i') }}
                                            {{ $event->title }}
                                        </a>
                                        <span class="badge text-bg-light">
                                            {{ $event->manager_id === auth()->id() ? 'správce' : 'účastník' }}
                                        </span>
                                    </div>
                                @endforeach
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="h5 mt-4">Události v tomto měsíci</h2>
    @if($events->isEmpty())
        <p class="text-muted">
            V tomto měsíci nemáte žádné události. Vstupenky na akce najdete v
            <a href="{{ route('events.index') }}">seznamu událostí</a>.
        </p>
    @else
        <ul class="list-group">
            @foreach($events as $event)
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a>
                        <div class="small text-muted">
                            {{ format_date_range($event->starts_at, $event->ends_at) }} · {{ $event->venue->name }}
                        </div>
                    </div>
                    <span class="badge text-bg-light">
                        {{ $event->manager_id === auth()->id() ? 'Správce události' : 'Účastník' }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
