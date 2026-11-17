<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EventRepository
{
    /**
     * @param array{q: string, category_ids: array<int, int>, city: string, from: ?CarbonImmutable, to: ?CarbonImmutable, price: string, availability: string, archive: bool, sort: string} $filters
     */
    public function search(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Event::query()
            ->with(['venue', 'category', 'ticketTypes'])
            ->approved();

        if (!$filters['archive']) {
            $query->where('starts_at', '>=', utc_now());
        }

        if ($filters['q'] !== '') {
            $like = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('venue', fn (Builder $venue) => $venue
                        ->where('name', 'like', $like)
                        ->orWhere('city', 'like', $like));
            });
        }

        if ($filters['category_ids'] !== []) {
            $query->whereIn('category_id', $filters['category_ids']);
        }
        if ($filters['city'] !== '') {
            $query->whereHas('venue', fn (Builder $venue) => $venue->where('city', $filters['city']));
        }
        if ($filters['from'] !== null) {
            $query->where('starts_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== null) {
            $query->where('starts_at', '<=', $filters['to']);
        }

        if ($filters['price'] === 'free') {
            $query->whereDoesntHave('ticketTypes', fn (Builder $type) => $type->where('price', '>', 0));
        } elseif ($filters['price'] === 'paid') {
            $query->whereHas('ticketTypes', fn (Builder $type) => $type->where('price', '>', 0));
        }

        if ($filters['availability'] === 'available') {
            $query->whereRaw('NOT ' . $this->fullExpression());
        } elseif ($filters['availability'] === 'full') {
            $query->whereRaw($this->fullExpression());
        }

        match ($filters['sort']) {
            'price' => $query->orderBy(
                DB::raw('(SELECT COALESCE(MIN(tt.price), 0) FROM ticket_types tt WHERE tt.event_id = events.id)')
            )->orderBy('starts_at'),
            'title' => $query->orderBy('title')->orderBy('starts_at'),
            default => $query->orderBy('starts_at')->orderBy('id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    public function cities(): array
    {
        return DB::table('events')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->where('events.status', Event::STATUS_APPROVED)
            ->distinct()
            ->orderBy('venues.city')
            ->pluck('venues.city')
            ->map(static fn ($city): string => (string) $city)
            ->all();
    }

    /**
     * Události, kde je uživatel potvrzený účastník nebo správce.
     */
    public function forCalendar(User $user, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Event::query()
            ->with(['venue', 'category'])
            ->approved()
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('manager_id', $user->id)
                    ->orWhereHas('registrations', fn (Builder $registration) => $registration
                        ->where('user_id', $user->id)
                        ->where('status', 'confirmed'));
            });

        if ($from !== null && $to !== null) {
            $query->whereBetween('starts_at', [$from, $to]);
        }

        return $query->orderBy('starts_at')->get();
    }

    public function exportable(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return Event::query()
            ->with(['venue', 'category'])
            ->approved()
            ->where('ends_at', '>=', utc_now())
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('manager_id', $user->id)
                    ->orWhereHas('registrations', fn (Builder $registration) => $registration
                        ->where('user_id', $user->id)
                        ->where('status', 'confirmed'));
            })
            ->orderBy('starts_at')
            ->get();
    }

    private function fullExpression(): string
    {
        return "(events.capacity IS NOT NULL AND (SELECT COUNT(*) FROM registrations r"
            . " WHERE r.event_id = events.id AND r.status IN ('pending', 'confirmed')) >= events.capacity)";
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
