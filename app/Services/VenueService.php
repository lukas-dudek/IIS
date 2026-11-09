<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\HttpException;
use App\Core\Upload;
use App\Core\ValidationException;
use App\Repositories\VenueRepository;
use RuntimeException;
use Throwable;

final class VenueService
{
    public function __construct(
        private VenueRepository $venues,
        private Upload $upload
    ) {
    }

    public function propose(array $data, ?array $photo, int $userId): int
    {
        $photoPath = $this->storePhoto($photo);
        try {
            return $this->venues->create(
                $data['name'],
                $data['street'],
                $data['city'],
                $data['postal_code'],
                $data['description'],
                $photoPath,
                'pending',
                $userId,
                null,
                null,
                null
            );
        } catch (Throwable $exception) {
            $this->upload->delete($photoPath);
            throw $exception;
        }
    }

    public function updateProposal(int $id, int $userId, array $data, ?array $photo): void
    {
        $venue = $this->venues->findOwned($id, $userId);
        if ($venue === null) {
            throw new HttpException(404, 'Místo nenalezeno.');
        }
        if (!in_array($venue['status'], ['pending', 'rejected'], true)) {
            throw new HttpException(403, 'Schválené místo může upravovat jen moderátor.');
        }

        $newPhoto = $this->storePhoto($photo);
        $photoPath = $newPhoto ?? $venue['photo_path'];
        try {
            $this->venues->updateProposal(
                $id,
                $data['name'],
                $data['street'],
                $data['city'],
                $data['postal_code'],
                $data['description'],
                $photoPath
            );
        } catch (Throwable $exception) {
            $this->upload->delete($newPhoto);
            throw $exception;
        }

        if ($newPhoto !== null) {
            $this->upload->delete($venue['photo_path']);
        }
    }

    public function createApproved(array $data, ?array $photo, int $moderatorId): int
    {
        $photoPath = $this->storePhoto($photo);
        try {
            return $this->venues->create(
                $data['name'],
                $data['street'],
                $data['city'],
                $data['postal_code'],
                $data['description'],
                $photoPath,
                'approved',
                $moderatorId,
                $moderatorId,
                now_utc(),
                null
            );
        } catch (Throwable $exception) {
            $this->upload->delete($photoPath);
            throw $exception;
        }
    }

    public function update(int $id, array $data, ?array $photo): void
    {
        $venue = $this->venues->find($id);
        if ($venue === null) {
            throw new HttpException(404, 'Místo nenalezeno.');
        }

        $newPhoto = $this->storePhoto($photo);
        $photoPath = $newPhoto ?? $venue['photo_path'];
        try {
            $this->venues->updateDetails(
                $id,
                $data['name'],
                $data['street'],
                $data['city'],
                $data['postal_code'],
                $data['description'],
                $photoPath
            );
        } catch (Throwable $exception) {
            $this->upload->delete($newPhoto);
            throw $exception;
        }

        if ($newPhoto !== null) {
            $this->upload->delete($venue['photo_path']);
        }
    }

    public function approve(int $id, int $moderatorId): void
    {
        $venue = $this->venues->find($id);
        if ($venue === null) {
            throw new HttpException(404, 'Místo nenalezeno.');
        }
        if ($venue['status'] !== 'pending') {
            throw new BusinessRuleException('Místo už není ve stavu čeká na schválení.');
        }

        $this->venues->approve($id, $moderatorId);
    }

    public function reject(int $id, int $moderatorId, string $reason): void
    {
        $venue = $this->venues->find($id);
        if ($venue === null) {
            throw new HttpException(404, 'Místo nenalezeno.');
        }
        if ($venue['status'] !== 'pending') {
            throw new BusinessRuleException('Místo už není ve stavu čeká na schválení.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'Důvod zamítnutí musí mít 3–255 znaků.']);
        }

        $this->venues->reject($id, $moderatorId, $reason);
    }

    public function delete(int $id): bool
    {
        if ($this->venues->find($id) === null) {
            throw new HttpException(404, 'Místo nenalezeno.');
        }

        return $this->venues->deleteIfUnused($id);
    }

    private function storePhoto(?array $photo): ?string
    {
        if ($photo === null) {
            return null;
        }

        $error = $photo['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error)) {
            throw new ValidationException(['photo' => 'Soubor se nepodařilo nahrát.']);
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        try {
            return $this->upload->store($photo, 'venues');
        } catch (RuntimeException $exception) {
            throw new ValidationException(['photo' => $exception->getMessage()]);
        }
    }
}
