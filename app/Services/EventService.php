<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use App\Repositories\EventRepository;
use App\Repositories\TicketTypeRepository;
use App\Repositories\VenueRepository;

final class EventService
{
    public function __construct(
        private EventRepository $events,
        private TicketTypeRepository $tickets,
        private VenueRepository $venues,
        private CategoryRepository $categories,
        private Database $database
    ) {
    }

    public function create(int $managerId, array $data, array $ticketRows, bool $submit): int
    {
        $data['manager_id'] = $managerId;

        return $this->saveInternal(null, $managerId, false, $data, $ticketRows, $submit);
    }

    public function save(int $id, int $managerId, bool $isAdmin, array $data, array $ticketRows, bool $submit): void
    {
        $this->saveInternal($id, $managerId, $isAdmin, $data, $ticketRows, $submit);
    }

    public function cancel(int $id, int $managerId, bool $isAdmin, string $reason): void
    {
        $this->database->transaction(function () use ($id, $managerId, $isAdmin, $reason): void {
            $event = $this->events->lock($id);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            if (!$isAdmin && (int) $event['manager_id'] !== $managerId) {
                throw new HttpException(403, 'Událost můžete spravovat jen jako její správce.');
            }
            if (!in_array($event['status'], ['draft', 'pending', 'approved'], true)) {
                throw new BusinessRuleException('Tuto událost už nelze zrušit.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
                throw new ValidationException(['reason' => 'Důvod zrušení musí mít 3–255 znaků.']);
            }

            $this->events->cancel($id, $reason);
            $this->events->cancelActiveRegistrations($id);
        });
    }

    public function approve(int $id, int $moderatorId): void
    {
        $this->database->transaction(function () use ($id, $moderatorId): void {
            $event = $this->events->lock($id);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            if ($event['status'] !== 'pending') {
                throw new BusinessRuleException('Událost už není ve stavu čeká na schválení.');
            }
            if ((int) $event['manager_id'] === $moderatorId) {
                throw new BusinessRuleException('Správce události ji nemůže sám schválit.');
            }

            $this->events->approve($id, $moderatorId);
        });
    }

    public function reject(int $id, int $moderatorId, string $reason): void
    {
        $this->database->transaction(function () use ($id, $moderatorId, $reason): void {
            $event = $this->events->lock($id);
            if ($event === null) {
                throw new HttpException(404, 'Událost nenalezena.');
            }
            if ($event['status'] !== 'pending') {
                throw new BusinessRuleException('Událost už není ve stavu čeká na schválení.');
            }
            if ((int) $event['manager_id'] === $moderatorId) {
                throw new BusinessRuleException('Správce události ji nemůže sám zamítnout.');
            }

            $reason = trim($reason);
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
                throw new ValidationException(['reason' => 'Důvod zamítnutí musí mít 3–255 znaků.']);
            }

            $this->events->reject($id, $moderatorId, $reason);
        });
    }

    private function saveInternal(
        ?int $eventId,
        int $actorId,
        bool $isAdmin,
        array $data,
        array $ticketRows,
        bool $submit
    ): int {
        return $this->database->transaction(function () use ($eventId, $actorId, $isAdmin, $data, $ticketRows, $submit): int {
            $event = null;
            if ($eventId !== null) {
                $event = $this->events->lock($eventId);
                if ($event === null) {
                    throw new HttpException(404, 'Událost nenalezena.');
                }
                if (!$isAdmin && (int) $event['manager_id'] !== $actorId) {
                    throw new HttpException(403, 'Událost můžete upravovat jen jako její správce.');
                }
                if ($event['status'] === 'cancelled') {
                    throw new BusinessRuleException('Zrušenou událost nelze upravovat.');
                }
                if ($submit && !in_array($event['status'], ['draft', 'rejected'], true)) {
                    throw new BusinessRuleException('Odeslat lze jen koncept nebo zamítnutou událost.');
                }
            }

            $errors = $this->validate($event, $data, $ticketRows, $submit);
            if ($errors !== []) {
                throw new ValidationException($errors);
            }

            $slug = $event !== null && $event['title'] === $data['title']
                ? $event['slug']
                : $this->generateUniqueSlug($data['title'], $eventId);

            $status = $event['status'] ?? 'draft';
            $submittedAt = $event['submitted_at'] ?? null;
            $clearReview = false;

            if ($submit) {
                $status = 'pending';
                $submittedAt = now_utc();
                $clearReview = true;
            } elseif ($event !== null && $event['status'] === 'approved'
                && $this->isSignificantChange($event, $data, $ticketRows)) {
                $status = 'pending';
                $submittedAt = now_utc();
                $clearReview = true;
            }

            if ($event === null) {
                $id = $this->events->create($data, $slug, $status, $submittedAt);
            } else {
                $this->events->updateFields($eventId, $data, $slug, $status, $submittedAt, $clearReview);
                $id = $eventId;
            }

            $this->syncTickets($id, $ticketRows);

            return $id;
        });
    }

    private function validate(?array $event, array $data, array $ticketRows, bool $submit): array
    {
        $errors = [];

        if (strtotime($data['starts_at']) === false || strtotime($data['ends_at']) === false) {
            $errors['starts_at'] = 'Zadejte platné datum a čas.';
        } elseif ($data['ends_at'] <= $data['starts_at']) {
            $errors['ends_at'] = 'Konec musí být po začátku.';
        } elseif ($submit && $data['starts_at'] <= now_utc()) {
            $errors['starts_at'] = 'Začátek události musí být v budoucnosti.';
        }

        if ($this->venues->findApproved((int) $data['venue_id']) === null) {
            $errors['venue_id'] = 'Vyberte schválené místo konání.';
        }
        if ($this->categories->findApproved((int) $data['category_id']) === null) {
            $errors['category_id'] = 'Vyberte schválenou kategorii.';
        }

        if ($data['capacity'] !== null && (int) $data['capacity'] < 1) {
            $errors['capacity'] = 'Kapacita musí být alespoň 1.';
        }
        if ($event !== null && $data['capacity'] !== null) {
            $active = $this->events->activeRegistrationCount((int) $event['id']);
            if ((int) $data['capacity'] < $active) {
                $errors['capacity'] = 'Kapacitu nelze snížit pod počet aktivních registrací (' . $active . ').';
            }
        }

        $existing = $event === null ? [] : $this->tickets->forEvent((int) $event['id']);
        $existingById = [];
        foreach ($existing as $type) {
            $existingById[(int) $type['id']] = $type;
        }

        $names = [];
        $finalCount = 0;
        foreach ($ticketRows as $index => $row) {
            $id = $row['id'] === null ? null : (int) $row['id'];
            $name = trim((string) $row['name']);
            if ($id !== null && !isset($existingById[$id])) {
                $errors['ticket_name_' . $index] = 'Typ vstupenky nepatří k této události.';
                continue;
            }
            if ($name === '') {
                if ($id !== null) {
                    if ($this->tickets->activeRegistrationCount($id) > 0) {
                        $errors['ticket_name_' . $index] = 'Typ vstupenky s registracemi nelze smazat.';
                    }
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

            if ($row['capacity'] !== null) {
                if ((int) $row['capacity'] < 1) {
                    $errors['ticket_capacity_' . $index] = 'Kapacita typu musí být alespoň 1.';
                } elseif ($id !== null) {
                    $active = $this->tickets->activeRegistrationCount($id);
                    if ((int) $row['capacity'] < $active) {
                        $errors['ticket_capacity_' . $index] = 'Kapacitu typu nelze snížit pod aktivní registrace (' . $active . ').';
                    }
                }
            }
        }

        if ($finalCount === 0 && ($submit || ($event !== null && $event['status'] !== 'draft'))) {
            $errors['tickets'] = 'Událost musí mít alespoň jeden typ vstupenky.';
        }

        return $errors;
    }

    private function syncTickets(int $eventId, array $ticketRows): void
    {
        foreach ($ticketRows as $row) {
            $id = $row['id'] === null ? null : (int) $row['id'];
            $name = trim((string) $row['name']);
            $price = str_replace(',', '.', trim((string) $row['price']));
            $capacity = $row['capacity'] === null ? null : (int) $row['capacity'];

            if ($id !== null) {
                if ($name === '') {
                    if (!$this->tickets->deleteIfUnused($id)) {
                        throw new ValidationException(['tickets' => 'Typ vstupenky s registracemi nelze smazat.']);
                    }
                    continue;
                }
                $this->tickets->update($id, $name, $price, $capacity);
                continue;
            }

            if ($name !== '') {
                $this->tickets->create($eventId, $name, $price, $capacity);
            }
        }
    }

    private function isSignificantChange(array $event, array $data, array $ticketRows): bool
    {
        $significantFields = ['title', 'starts_at', 'ends_at', 'venue_id', 'category_id', 'capacity'];
        foreach ($significantFields as $field) {
            $old = $event[$field];
            $new = $data[$field];
            if ($field === 'capacity') {
                $old = $old === null ? null : (int) $old;
                $new = $new === null ? null : (int) $new;
            } elseif ($field === 'venue_id' || $field === 'category_id') {
                $old = (int) $old;
                $new = (int) $new;
            }
            if ($old !== $new) {
                return true;
            }
        }

        $existing = $this->tickets->forEvent((int) $event['id']);

        return $this->ticketSignature($existing) !== $this->ticketSignature($ticketRows);
    }

    private function ticketSignature(array $rows): string
    {
        $normalized = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' && ($row['id'] ?? null) === null) {
                continue;
            }
            $normalized[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'name' => $name,
                'price' => number_format((float) str_replace(',', '.', (string) $row['price']), 2, '.', ''),
                'capacity' => $row['capacity'] === null ? null : (int) $row['capacity'],
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            return ($a['id'] ?? PHP_INT_MAX) <=> ($b['id'] ?? PHP_INT_MAX);
        });

        return json_encode($normalized) ?: '';
    }

    private function generateUniqueSlug(string $title, ?int $exceptId): string
    {
        $base = slugify($title, 180);
        $slug = $base;
        $suffix = 1;

        while ($this->events->slugExists($slug, $exceptId)) {
            $suffix++;
            $suffixText = '-' . $suffix;
            $slug = mb_substr($base, 0, 180 - strlen($suffixText)) . $suffixText;
        }

        return $slug;
    }
}
