@extends('layouts.app')

@section('title', 'Moje události')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Moje události</h1>
        <a class="btn btn-primary" href="{{ route('events.create') }}">Založit událost</a>
    </div>

    @if($events->isEmpty())
        <div class="alert alert-info">
            Zatím jste nezaložili žádnou událost. <a href="{{ route('events.create') }}">Založte první událost</a>
            a odešlete ji ke schválení.
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-white">
                <thead>
                <tr>
                    <th scope="col">Událost</th>
                    <th scope="col">Termín</th>
                    <th scope="col">Stav</th>
                    <th scope="col">Registrace</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($events as $event)
                    <tr>
                        <td>
                            <a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a>
                            <div class="small text-muted">{{ $event->venue->name }} · {{ $event->category->name }}</div>
                        </td>
                        <td class="small">{{ format_date_range($event->starts_at, $event->ends_at) }}</td>
                        <td>
                            <span class="badge {{ status_badge_class($event->status) }}">
                                {{ event_status_label($event->status) }}
                            </span>
                        </td>
                        <td class="small">
                            {{ $event->active_registrations_count }}
                            @if($event->capacity !== null)
                                / {{ $event->capacity }}
                            @endif
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('events.edit', $event) }}">Upravit</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $events->links() }}</div>
    @endif
@endsection
