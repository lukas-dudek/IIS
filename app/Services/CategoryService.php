<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Repositories\CategoryRepository;
use PDOException;

final class CategoryService
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    public function find(int $id): ?array
    {
        return $this->categories->find($id);
    }

    public function findApproved(int $id): ?array
    {
        return $this->categories->findApproved($id);
    }

    public function findOwned(int $id, int $userId): ?array
    {
        return $this->categories->findOwned($id, $userId);
    }

    public function proposalsForUser(int $userId): array
    {
        return $this->categories->proposalsForUser($userId);
    }

    public function descendantIds(int $id): array
    {
        return $this->categories->descendantIds($id);
    }

    public function approvedTree(): array
    {
        return $this->buildTree($this->categories->approved());
    }

    public function approvedOptions(?int $excludeId = null): array
    {
        $excluded = [];
        if ($excludeId !== null) {
            $excluded[$excludeId] = true;
            foreach ($this->categories->descendantIds($excludeId) as $id) {
                $excluded[$id] = true;
            }
        }

        $result = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$result, $excluded): void {
            foreach ($nodes as $node) {
                $id = (int) $node['id'];
                if (isset($excluded[$id])) {
                    continue;
                }
                $result[] = ['id' => $id, 'name' => $node['name'], 'depth' => $depth];
                $walk($node['children'], $depth + 1);
            }
        };
        $walk($this->approvedTree(), 1);

        return $result;
    }

    public function moderationFlat(): array
    {
        return $this->flatten($this->buildTree($this->categories->allForModeration()));
    }

    public function createProposal(array $data, int $userId): int
    {
        $errors = $this->validateParent($data['parent_id'], null);
        if ($this->categories->nameExists($data['name'], $data['parent_id'])) {
            $errors['name'] = 'Kategorie se stejným názvem už v této části stromu existuje.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $slug = $this->generateUniqueSlug($data['name']);

        try {
            return $this->categories->create(
                $data['name'],
                $slug,
                $data['parent_id'],
                $data['description'],
                'pending',
                $userId,
                null,
                null,
                null
            );
        } catch (PDOException $exception) {
            $this->throwIfDuplicateSlug($exception);
        }
    }

    public function updateProposal(int $id, int $userId, array $data): void
    {
        $category = $this->categories->findOwned($id, $userId);
        if ($category === null) {
            throw new HttpException(404, 'Kategorie nenalezena.');
        }
        if (!in_array($category['status'], ['pending', 'rejected'], true)) {
            throw new HttpException(403, 'Schválenou kategorii může upravovat jen moderátor.');
        }

        $errors = $this->validateParent($data['parent_id'], $id);
        if ($this->categories->nameExists($data['name'], $data['parent_id'], $id)) {
            $errors['name'] = 'Kategorie se stejným názvem už v této části stromu existuje.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $this->categories->update(
                $id,
                $data['name'],
                $this->generateUniqueSlug($data['name'], $id),
                $data['parent_id'],
                $data['description'],
                'pending',
                null,
                null,
                null
            );
        } catch (PDOException $exception) {
            $this->throwIfDuplicateSlug($exception);
        }
    }

    public function createApproved(array $data, int $moderatorId): int
    {
        $errors = $this->validateParent($data['parent_id'], null);
        if ($this->categories->nameExists($data['name'], $data['parent_id'])) {
            $errors['name'] = 'Kategorie se stejným názvem už v této části stromu existuje.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            return $this->categories->create(
                $data['name'],
                $this->generateUniqueSlug($data['name']),
                $data['parent_id'],
                $data['description'],
                'approved',
                $moderatorId,
                $moderatorId,
                now_utc(),
                null
            );
        } catch (PDOException $exception) {
            $this->throwIfDuplicateSlug($exception);
        }
    }

    public function updateCategory(int $id, array $data): void
    {
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Kategorie nenalezena.');
        }

        $errors = $this->validateParent($data['parent_id'], $id);
        if ($this->categories->nameExists($data['name'], $data['parent_id'], $id)) {
            $errors['name'] = 'Kategorie se stejným názvem už v této části stromu existuje.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $this->categories->update(
                $id,
                $data['name'],
                $this->generateUniqueSlug($data['name'], $id),
                $data['parent_id'],
                $data['description'],
                $category['status'],
                $category['reviewed_by'] === null ? null : (int) $category['reviewed_by'],
                $category['reviewed_at'],
                $category['rejection_reason']
            );
        } catch (PDOException $exception) {
            $this->throwIfDuplicateSlug($exception);
        }
    }

    public function approve(int $id, int $moderatorId): void
    {
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Kategorie nenalezena.');
        }
        if ($category['status'] !== 'pending') {
            throw new BusinessRuleException('Kategorie už není ve stavu čeká na schválení.');
        }
        if ($category['parent_id'] !== null) {
            $parent = $this->categories->find((int) $category['parent_id']);
            if ($parent === null || $parent['status'] !== 'approved') {
                throw new BusinessRuleException('Rodičovská kategorie musí být schválená.');
            }
        }

        $this->categories->approve($id, $moderatorId);
    }

    public function reject(int $id, int $moderatorId, string $reason): void
    {
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Kategorie nenalezena.');
        }
        if ($category['status'] !== 'pending') {
            throw new BusinessRuleException('Kategorie už není ve stavu čeká na schválení.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'Důvod zamítnutí musí mít 3–255 znaků.']);
        }

        $this->categories->reject($id, $moderatorId, $reason);
    }

    public function delete(int $id): bool
    {
        if ($this->categories->find($id) === null) {
            throw new HttpException(404, 'Kategorie nenalezena.');
        }

        return $this->categories->deleteIfUnused($id);
    }

    private function validateParent(?int $parentId, ?int $categoryId): array
    {
        if ($parentId === null) {
            return [];
        }
        if ($parentId <= 0) {
            return ['parent_id' => 'Vyberte platnou rodičovskou kategorii.'];
        }

        $parent = $this->categories->find($parentId);
        if ($parent === null || $parent['status'] !== 'approved') {
            return ['parent_id' => 'Rodičovská kategorie musí být schválená.'];
        }

        if ($categoryId !== null) {
            if ($parentId === $categoryId
                || in_array($parentId, $this->categories->descendantIds($categoryId), true)) {
                return ['parent_id' => 'Kategorie nesmí být přesunuta pod sebe nebo pod svého potomka.'];
            }
        }

        $depth = $this->categories->depthOf($parentId) + 1;
        if ($depth > 4) {
            return ['parent_id' => 'Kategorie může být nejvýše 4 úrovně hluboko.'];
        }

        if ($categoryId !== null) {
            $height = $this->categories->heightOf($categoryId);
            if ($depth + $height - 1 > 4) {
                return ['parent_id' => 'Přesun by překročil maximální hloubku 4.'];
            }
        }

        return [];
    }

    private function generateUniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = slugify($name, 100);
        $slug = $base;
        $suffix = 1;

        while ($this->categories->slugExists($slug, $exceptId)) {
            $suffix++;
            $suffixText = '-' . $suffix;
            $slug = mb_substr($base, 0, 100 - strlen($suffixText)) . $suffixText;
        }

        return $slug;
    }

    private function buildTree(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $children = [];
        $roots = [];
        foreach ($byId as $id => $row) {
            $parentId = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            if ($parentId !== null && isset($byId[$parentId])) {
                $children[$parentId][] = $row;
            } else {
                $roots[] = $row;
            }
        }

        $build = function (array $row) use (&$build, $children): array {
            $row['children'] = [];
            foreach ($children[(int) $row['id']] ?? [] as $child) {
                $row['children'][] = $build($child);
            }

            return $row;
        };

        return array_map($build, $roots);
    }

    private function flatten(array $tree, int $depth = 1): array
    {
        $result = [];
        foreach ($tree as $node) {
            $result[] = $node + ['depth' => $depth];
            $result = array_merge($result, $this->flatten($node['children'], $depth + 1));
        }

        return $result;
    }

    private function throwIfDuplicateSlug(PDOException $exception): never
    {
        if ((string) $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'uq_categories_slug')) {
            throw new ValidationException(['name' => 'Kategorii se nepodařilo uložit, zkuste to znovu.']);
        }

        throw $exception;
    }
}
