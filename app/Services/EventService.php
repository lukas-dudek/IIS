<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Venue;
use App\Support\Slug;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventService
{
    /**
     * @param array<int, array{id: ?int, name: string, price: string, capacity: ?string}> $tickets
     */
    public function create(int $managerId, array $data, array $tickets, bool $submit): Event
    {
        return DB::transaction(function () use ($managerId, $data, $tickets, $submit): Event {
            $errors = $this->validate($data, $tickets, null, $submit);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $event = Event::query()->create([
                'slug' => $this->uniqueSlug((string) $data['title']),
                'title' => trim((string) $data['title']),
                'description' => trim((string) $data['description']),
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'venue_id' => (int) $data['venue_id'],
                'category_id' => (int) $data['category_id'],
                'capacity' => $this->nullableInt($data['capacity'] ?? null),
                'cover_path' => $data['cover_path'] ?? null,
                'payment_note' => $this->nullable((string) ($data['payment_note'] ?? '')),
                'status' => $submit ? Event::STATUS_PENDING : Event::STATUS_DRAFT,
                'manager_id' => $managerId,
                'submitted_at' => $submit ? utc_now() : null,
            ]);

            $this->syncTickets($event, $tickets);

            return $event->refresh();
        });
    }

    /**
     * @param array<int, array{id: ?int, name: string, price: string, capacity: ?string}> $tickets
     */
    public function update(Event $event, array $data, array $tickets, bool $submit): Event
    {
        return DB::transaction(function () use ($event, $data, $tickets, $submit): Event {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new DomainRuleException('Zrušenou událost nelze upravovat.');
            }
            if ($submit && !in_array($locked->status, [Event::STATUS_DRAFT, Event::STATUS_REJECTED], true)) {
                throw new DomainRuleException('Odeslat ke schválení lze jen koncept nebo zamítnutou událost.');
            }

            $errors = $this->validate($data, $tickets, $locked, $submit);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $status = $locked->status;
            $submittedAt = $locked->submitted_at;
            $clearReview = false;

            if ($submit) {
                $status = Event::STATUS_PENDING;
                $submittedAt = utc_now();
                $clearReview = true;
            } elseif ($locked->isApproved() && $this->isSignificantChange($locked, $data, $tickets)) {
                $status = Event::STATUS_PENDING;
                $submittedAt = utc_now();
                $clearReview = true;
            }

            $locked->forceFill([
                'slug' => $locked->title === trim((string) $data['title'])
                    ? $locked->slug
                    : $this->uniqueSlug((string) $data['title'], $locked->id),
                'title' => trim((string) $data['title']),
                'description' => trim((string) $data['description']),
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'venue_id' => (int) $data['venue_id'],
                'category_id' => (int) $data['category_id'],
                'capacity' => $this->nullableInt($data['capacity'] ?? null),
                'cover_path' => $data['cover_path'] ?? $locked->cover_path,
                'payment_note' => $this->nullable((string) ($data['payment_note'] ?? '')),
                'status' => $status,
                'submitted_at' => $submittedAt,
                'reviewed_by' => $clearReview ? null : $locked->reviewed_by,
                'reviewed_at' => $clearReview ? null : $locked->reviewed_at,
                'rejection_reason' => $clearReview ? null : $locked->rejection_reason,
            ])->save();

            $this->syncTickets($locked, $tickets);

            return $locked->refresh();
        });
    }

    public function submit(Event $event): Event
    {
        return DB::transaction(function () use ($event): Event {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [Event::STATUS_DRAFT, Event::STATUS_REJECTED], true)) {
                throw new DomainRuleException('Odeslat ke schválení lze jen koncept nebo zamítnutou událost.');
            }

            $tickets = $this->ticketInputFromModel($locked);
            $errors = $this->validate($this->modelToFormData($locked), $tickets, $locked, true);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $locked->forceFill([
                'status' => Event::STATUS_PENDING,
                'submitted_at' => utc_now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ])->save();

            return $locked;
        });
    }

    public function cancel(Event $event, string $reason): Event
    {
        return DB::transaction(function () use ($event, $reason): Event {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (!in_array($locked->status, [Event::STATUS_DRAFT, Event::STATUS_PENDING, Event::STATUS_APPROVED], true)) {
                throw new DomainRuleException('Tuto událost už nelze zrušit.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
                throw ValidationException::withMessages(['reason' => 'Důvod zrušení musí mít 3–255 znaků.']);
            }

            $locked->forceFill([
                'status' => Event::STATUS_CANCELLED,
                'cancelled_reason' => $reason,
            ])->save();

            Registration::query()
                ->where('event_id', $locked->id)
                ->active()
                ->update([
                    'status' => Registration::STATUS_CANCELLED,
                    'cancelled_at' => utc_now(),
                ]);

            return $locked;
        });
    }

    public function approve(Event $event, int $moderatorId): Event
    {
        return DB::transaction(function () use ($event, $moderatorId): Event {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Event::STATUS_PENDING) {
                throw new DomainRuleException('Událost už není ve stavu čeká na schválení.');
            }
            if ($locked->manager_id === $moderatorId) {
                throw new DomainRuleException('Správce události ji nemůže sám schválit.');
            }

            $locked->forceFill([
                'status' => Event::STATUS_APPROVED,
                'reviewed_by' => $moderatorId,
                'reviewed_at' => utc_now(),
                'rejection_reason' => null,
            ])->save();

            return $locked;
        });
    }

    public function reject(Event $event, int $moderatorId, string $reason): Event
    {
        return DB::transaction(function () use ($event, $moderatorId, $reason): Event {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Event::STATUS_PENDING) {
                throw new DomainRuleException('Událost už není ve stavu čeká na schválení.');
            }
            if ($locked->manager_id === $moderatorId) {
                throw new DomainRuleException('Správce události ji nemůže sám zamítnout.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
                throw ValidationException::withMessages(['reason' => 'Důvod zamítnutí musí mít 3–255 znaků.']);
            }

            $locked->forceFill([
                'status' => Event::STATUS_REJECTED,
                'reviewed_by' => $moderatorId,
                'reviewed_at' => utc_now(),
                'rejection_reason' => $reason,
            ])->save();

            return $locked;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array{id: ?int, name: string, price: string, capacity: ?string}> $tickets
     * @return array<string, string>
     */
    private function validate(array $data, array $tickets, ?Event $event, bool $submit): array
    {
        $errors = [];
        $startsAt = $data['starts_at'];
        $endsAt = $data['ends_at'];

        if (!$startsAt instanceof \DateTimeInterface || !$endsAt instanceof \DateTimeInterface) {
            $errors['starts_at'] = 'Zadejte platné datum a čas.';
        } elseif ($endsAt <= $startsAt) {
            $errors['ends_at'] = 'Konec musí být po začátku.';
        } elseif ($submit && CarbonImmutable::instance($startsAt)->setTimezone('UTC')->lte(utc_now())) {
            $errors['starts_at'] = 'Začátek události musí být v budoucnosti.';
        }

        $venue = Venue::query()->find($data['venue_id'] ?? 0);
        if ($venue === null || !$venue->isApproved()) {
            $errors['venue_id'] = 'Vyberte schválené místo konání.';
        }
        $category = Category::query()->find($data['category_id'] ?? 0);
        if ($category === null || !$category->isApproved()) {
            $errors['category_id'] = 'Vyberte schválenou kategorii.';
        }

        $capacity = $this->nullableInt($data['capacity'] ?? null);
        if ($capacity !== null && $capacity < 1) {
            $errors['capacity'] = 'Kapacita musí být alespoň 1.';
        } elseif ($event !== null && $capacity !== null) {
            $active = $this->activeRegistrationCount($event->id);
            if ($capacity < $active) {
                $errors['capacity'] = 'Kapacitu nelze snížit pod počet aktivních registrací (' . $active . ').';
            }
        }

        $existing = $event === null
            ? collect()
            : $event->ticketTypes()->get()->keyBy('id');

        $names = [];
        $finalCount = 0;
        foreach ($tickets as $index => $row) {
            $id = $row['id'] === null ? null : (int) $row['id'];
            $name = trim((string) $row['name']);

            if ($id !== null && !$existing->has($id)) {
                $errors['ticket_name_' . $index] = 'Typ vstupenky nepatří k této události.';
                continue;
            }

            if ($name === '') {
                if ($id !== null && $this->activeRegistrationCountForTicket($id) > 0) {
                    $errors['ticket_name_' . $index] = 'Typ vstupenky s registracemi nelze smazat.';
                }
                continue;
            }

            $finalCount++;
            $key = mb_strtolower($name);
            if (isset($names[$key])) {
                $errors['ticket_name_' . $index] = 'Název typu vstupenky musí být v rámci události unikátní.';
            }
            $names[$key] = true;

            if (mb_strlen($name) > 60) {
                $errors['ticket_name_' . $index] = 'Název typu vstupenky může mít nejvýše 60 znaků.';
            }

            $price = str_replace(',', '.', trim((string) $row['price']));
            if (preg_match('/^\d+(?:\.\d{1,2})?$/', $price) !== 1 || (float) $price > 100000) {
                $errors['ticket_price_' . $index] = 'Cena musí být 0–100000 s nejvýše dvěma desetinnými místy.';
            }

            $ticketCapacity = $this->nullableInt($row['capacity']);
            if ($ticketCapacity !== null) {
                if ($ticketCapacity < 1) {
                    $errors['ticket_capacity_' . $index] = 'Kapacita typu musí být alespoň 1.';
                } elseif ($id !== null) {
                    $active = $this->activeRegistrationCountForTicket($id);
                    if ($ticketCapacity < $active) {
                        $errors['ticket_capacity_' . $index] = 'Kapacitu typu nelze snížit pod aktivní registrace (' . $active . ').';
                    }
                }
            }
        }

        if ($finalCount === 0 && ($submit || ($event !== null && !$event->isDraft()))) {
            $errors['tickets'] = 'Událost musí mít alespoň jeden typ vstupenky.';
        }

        return $errors;
    }

    /**
     * @param array<int, array{id: ?int, name: string, price: string, capacity: ?string}> $tickets
     */
    private function syncTickets(Event $event, array $tickets): void
    {
        foreach ($tickets as $row) {
            $id = $row['id'] === null ? null : (int) $row['id'];
            $name = trim((string) $row['name']);
            $price = str_replace(',', '.', trim((string) $row['price']));
            $capacity = $this->nullableInt($row['capacity']);

            if ($id !== null) {
                if ($name === '') {
                    $this->deleteTicket($id);
                    continue;
                }
                $event->ticketTypes()->whereKey($id)->update([
                    'name' => $name,
                    'price' => number_format((float) $price, 2, '.', ''),
                    'capacity' => $capacity,
                    'updated_at' => utc_now(),
                ]);
                continue;
            }

            if ($name !== '') {
                $event->ticketTypes()->create([
                    'name' => $name,
                    'price' => number_format((float) $price, 2, '.', ''),
                    'capacity' => $capacity,
                ]);
            }
        }
    }

    private function deleteTicket(int $ticketTypeId): void
    {
        $deleted = DB::table('ticket_types')
            ->where('id', $ticketTypeId)
            ->whereNotExists(static fn ($query) => $query
                ->select(DB::raw('1'))
                ->from('registrations')
                ->whereColumn('registrations.ticket_type_id', 'ticket_types.id'))
            ->delete();

        if ($deleted === 0) {
            throw new DomainRuleException('Typ vstupenky s registracemi nelze smazat.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array{id: ?int, name: string, price: string, capacity: ?string}> $tickets
     */
    private function isSignificantChange(Event $event, array $data, array $tickets): bool
    {
        foreach (Event::SIGNIFICANT_FIELDS as $field) {
            $old = $event->getAttribute($field);
            $new = $data[$field];

            if ($field === 'capacity') {
                if ($this->nullableInt($old) !== $this->nullableInt($new)) {
                    return true;
                }
                continue;
            }

            if ($field === 'venue_id' || $field === 'category_id') {
                if ((int) $old !== (int) $new) {
                    return true;
                }
                continue;
            }

            if ($field === 'starts_at' || $field === 'ends_at') {
                $oldMoment = $old instanceof \DateTimeInterface
                    ? CarbonImmutable::instance($old)->setTimezone('UTC')
                    : null;
                $newMoment = $new instanceof \DateTimeInterface
                    ? CarbonImmutable::instance($new)->setTimezone('UTC')
                    : null;
                if ($oldMoment?->getTimestamp() !== $newMoment?->getTimestamp()) {
                    return true;
                }
                continue;
            }

            if ((string) $old !== (string) $new) {
                return true;
            }
        }

        $existing = $event->ticketTypes()->get()->keyBy('id');

        return $this->ticketSignature($existing) !== $this->ticketSignatureFromInput($tickets);
    }

    private function ticketSignature(\Illuminate\Support\Collection $types): string
    {
        $normalized = [];
        foreach ($types as $type) {
            $normalized[] = [
                'id' => (int) $type->id,
                'name' => $type->name,
                'price' => number_format((float) $type->price, 2, '.', ''),
                'capacity' => $type->capacity === null ? null : (int) $type->capacity,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return (string) json_encode($normalized);
    }

    /**
     * @param array<int, array{id: ?int, name: string, price: string, capacity: ?string}> $tickets
     */
    private function ticketSignatureFromInput(array $tickets): string
    {
        $normalized = [];
        foreach ($tickets as $row) {
            $name = trim((string) $row['name']);
            if ($name === '' && $row['id'] === null) {
                continue;
            }
            $price = str_replace(',', '.', trim((string) $row['price']));
            $normalized[] = [
                'id' => $row['id'] === null ? null : (int) $row['id'],
                'name' => $name,
                'price' => number_format((float) $price, 2, '.', ''),
                'capacity' => $this->nullableInt($row['capacity']),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => ($a['id'] ?? PHP_INT_MAX) <=> ($b['id'] ?? PHP_INT_MAX));

        return (string) json_encode($normalized);
    }

    /**
     * @return array<int, array{id: ?int, name: string, price: string, capacity: ?string}>
     */
    /**
     * @return array<string, mixed>
     */
    private function modelToFormData(Event $event): array
    {
        return [
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'venue_id' => $event->venue_id,
            'category_id' => $event->category_id,
            'capacity' => $event->capacity,
        ];
    }

    private function ticketInputFromModel(Event $event): array
    {
        return $event->ticketTypes()->get()->map(static fn ($type): array => [
            'id' => (int) $type->id,
            'name' => (string) $type->name,
            'price' => (string) $type->price,
            'capacity' => $type->capacity === null ? null : (string) $type->capacity,
        ])->all();
    }

    private function activeRegistrationCount(int $eventId): int
    {
        return Registration::query()->where('event_id', $eventId)->active()->count();
    }

    private function activeRegistrationCountForTicket(int $ticketTypeId): int
    {
        return Registration::query()->where('ticket_type_id', $ticketTypeId)->active()->count();
    }

    private function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = Slug::make($title, 180);
        $slug = $base;
        $counter = 1;

        while (Event::query()->where('slug', $slug)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists()) {
            $counter++;
            $suffix = '-' . $counter;
            $slug = mb_substr($base, 0, 180 - strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
