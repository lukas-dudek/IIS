@props(['event'])

<div class="card h-100 shadow-sm">
    @if($event->cover_path)
        <img src="{{ Storage::disk('public')->url($event->cover_path) }}" class="card-img-top object-fit-cover"
             style="height: 160px" alt="">
    @endif
    <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
            <h3 class="h6 mb-0">
                <a class="stretched-link text-decoration-none"
                   href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a>
            </h3>
            <span class="badge {{ status_badge_class($event->status) }}">{{ event_status_label($event->status) }}</span>
        </div>
        <p class="small text-muted mb-2">
            {{ format_date_range($event->starts_at, $event->ends_at) }}
        </p>
        <p class="small mb-2">
            {{ $event->venue?->name }} · {{ $event->venue?->city }}
            <br>
            <span class="text-muted">{{ $event->category?->name }}</span>
        </p>
        <p class="small mb-0 mt-auto">
            @if($event->ticketTypes->isEmpty())
                <span class="text-muted">Vstupenky nejsou zadány</span>
            @elseif($event->ticketTypes->contains(fn ($type) => (float) $type->price > 0))
                Vstupné od {{ format_money($event->ticketTypes->min('price')) }}
            @else
                Vstup zdarma
            @endif
            @if($event->capacity !== null)
                <span class="text-muted">· kapacita {{ $event->capacity }}</span>
            @endif
        </p>
    </div>
</div>
