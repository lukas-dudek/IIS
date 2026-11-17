<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Venue;
use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class VenueService
{
    public function __construct(private ImageUpload $upload)
    {
    }

    public function propose(array $data, ?UploadedFile $photo, int $userId): Venue
    {
        $photoPath = $this->storePhoto($photo);

        try {
            return Venue::query()->create([
                'name' => trim((string) $data['name']),
                'street' => trim((string) $data['street']),
                'city' => trim((string) $data['city']),
                'postal_code' => $this->nullable($data['postal_code'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'photo_path' => $photoPath,
                'status' => Venue::STATUS_PENDING,
                'proposed_by' => $userId,
            ]);
        } catch (\Throwable $exception) {
            $this->upload->delete($photoPath);
            throw $exception;
        }
    }

    public function createApproved(array $data, ?UploadedFile $photo, int $moderatorId): Venue
    {
        $venue = $this->propose($data, $photo, $moderatorId);
        $venue->forceFill([
            'status' => Venue::STATUS_APPROVED,
            'reviewed_by' => $moderatorId,
            'reviewed_at' => utc_now(),
        ])->save();

        return $venue;
    }

    public function updateProposal(Venue $venue, array $data, ?UploadedFile $photo): void
    {
        if (!$venue->isApproved()) {
            $this->updateDetails($venue, $data, $photo);
            if ($venue->status !== Venue::STATUS_PENDING) {
                $venue->forceFill(['status' => Venue::STATUS_PENDING, 'rejection_reason' => null])->save();
            }

            return;
        }

        $this->updateDetails($venue, $data, $photo);
    }

    public function updateDetails(Venue $venue, array $data, ?UploadedFile $photo): void
    {
        $newPhoto = $this->storePhoto($photo);
        $oldPhoto = $venue->photo_path;

        try {
            $venue->forceFill([
                'name' => trim((string) $data['name']),
                'street' => trim((string) $data['street']),
                'city' => trim((string) $data['city']),
                'postal_code' => $this->nullable($data['postal_code'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'photo_path' => $newPhoto ?? $oldPhoto,
            ])->save();
        } catch (\Throwable $exception) {
            $this->upload->delete($newPhoto);
            throw $exception;
        }

        if ($newPhoto !== null) {
            $this->upload->delete($oldPhoto);
        }
    }

    public function approve(Venue $venue, int $moderatorId): void
    {
        if (!$venue->isPending()) {
            throw new DomainRuleException('Místo už není ve stavu čeká na schválení.');
        }

        $venue->forceFill([
            'status' => Venue::STATUS_APPROVED,
            'reviewed_by' => $moderatorId,
            'reviewed_at' => utc_now(),
            'rejection_reason' => null,
        ])->save();
    }

    public function reject(Venue $venue, int $moderatorId, string $reason): void
    {
        if (!$venue->isPending()) {
            throw new DomainRuleException('Místo už není ve stavu čeká na schválení.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new DomainRuleException('Důvod zamítnutí musí mít 3–255 znaků.');
        }

        $venue->forceFill([
            'status' => Venue::STATUS_REJECTED,
            'reviewed_by' => $moderatorId,
            'reviewed_at' => utc_now(),
            'rejection_reason' => $reason,
        ])->save();
    }

    public function delete(Venue $venue): void
    {
        if ($venue->events()->exists()) {
            throw new DomainRuleException('Místo nelze smazat, protože je použito u události.');
        }

        $photo = $venue->photo_path;
        $venue->delete();
        $this->upload->delete($photo);
    }

    private function storePhoto(?UploadedFile $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        try {
            return $this->upload->store($photo, 'venues');
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['photo' => $exception->getMessage()]);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
