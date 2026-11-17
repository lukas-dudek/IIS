@extends('layouts.app')

@section('title', 'Moderace událostí')

@section('content')
    @include('moderation.nav')

    <h1 class="h4 mb-3">Události ke schválení</h1>

    @if($events->isEmpty())
        <div class="alert alert-info">Fronta je prázdná, žádná událost nečeká na schválení.</div>
    @else
        <div class="table-responsive">
            <table class="table align-middle bg-white">
                <thead>
                <tr>
                    <th scope="col">Událost</th>
                    <th scope="col">Správce</th>
                    <th scope="col">Termín</th>
                    <th scope="col">Odesláno</th>
                    <th scope="col"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($events as $event)
                    <tr>
                        <td>
                            <a href="{{ route('moderation.event', $event) }}">{{ $event->title }}</a>
                            <div class="small text-muted">
                                {{ $event->venue->name }} · {{ $event->category->path() }}
                            </div>
                        </td>
                        <td class="small">{{ $event->manager->name }}</td>
                        <td class="small">{{ format_date_range($event->starts_at, $event->ends_at) }}</td>
                        <td class="small">{{ format_datetime($event->submitted_at) }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('moderation.event', $event) }}">
                                Posoudit
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $events->links() }}</div>
    @endif
@endsection
