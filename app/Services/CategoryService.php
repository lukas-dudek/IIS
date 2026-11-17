<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Category;
use App\Models\Event;
use App\Support\Slug;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CategoryService
{
    /**
     * @return Collection<int, Category>
     */
    public function approvedTree(): Collection
    {
        $rows = Category::query()->approved()->orderBy('name')->get();

        return $this->buildTree($rows);
    }

    /**
     * @return array<int, array{id: int, label: string, depth: int}>
     */
    public function approvedOptions(?Category $exclude = null): array
    {
        $excluded = $exclude === null ? [] : $this->subtreeIds($exclude->id);
        if ($exclude !== null) {
            $excluded[] = $exclude->id;
        }

        $options = [];
        $this->walkOptions($this->approvedTree(), 1, $excluded, $options);

        return $options;
    }

    /**
     * @return array<int, array{model: Category, depth: int}>
     */
    public function moderationRows(): array
    {
        $rows = Category::query()->orderBy('name')->with('parent')->get();
        $tree = $this->buildTree($rows);
        $flat = [];
        $this->flattenRows($tree, 1, $flat);

        return $flat;
    }

    /**
     * @return array<int, Category>
     */
    public function subtreeIds(int $categoryId): array
    {
        $all = Category::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($all->get($current, []) as $child) {
                $childId = (int) $child->id;
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * Vrací hloubku kategorie (kořen = 1).
     */
    public function depthOf(Category $category): int
    {
        $depth = 1;
        $parent = $category->parent;

        while ($parent !== null && $depth < Category::MAX_DEPTH + 1) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    /**
     * @return array<int, string>
     */
    public function validatePosition(?int $parentId, ?Category $category = null, ?string $name = null): array
    {
        $errors = [];

        if ($name !== null) {
            $trimmed = trim($name);
            if ($trimmed === '') {
                $errors['name'] = 'Zadejte název kategorie.';
            } elseif (mb_strlen($trimmed) > 80) {
                $errors['name'] = 'Název kategorie může mít nejvýše 80 znaků.';
            } elseif ($this->nameExists($trimmed, $parentId, $category?->id)) {
                $errors['name'] = 'Kategorie se stejným názvem už v této části stromu existuje.';
            }
        }

        if ($parentId === null) {
            return $errors;
        }

        $parent = Category::query()->find($parentId);
        if ($parent === null || !$parent->isApproved()) {
            $errors['parent_id'] = 'Rodičovská kategorie musí být schválená.';

            return $errors;
        }

        if ($this->depthOf($parent) + 1 > Category::MAX_DEPTH) {
            $errors['parent_id'] = 'Kategorie může být nejvýše ' . Category::MAX_DEPTH . ' úrovně hluboko.';

            return $errors;
        }

        if ($category !== null) {
            if ($parentId === $category->id || in_array($parentId, $this->subtreeIds($category->id), true)) {
                $errors['parent_id'] = 'Kategorii nelze přesunout pod sebe nebo pod svého potomka.';

                return $errors;
            }

            $height = $this->heightOf($category);
            if ($this->depthOf($parent) + $height > Category::MAX_DEPTH) {
                $errors['parent_id'] = 'Přesun by překročil maximální hloubku ' . Category::MAX_DEPTH . '.';
            }
        }

        return $errors;
    }

    public function heightOf(Category $category): int
    {
        $childrenByParent = Category::query()->get(['id', 'parent_id'])->groupBy('parent_id');

        $walk = function (int $id, int $level) use (&$walk, $childrenByParent): int {
            $max = $level;
            foreach ($childrenByParent->get($id, []) as $child) {
                $max = max($max, $walk((int) $child->id, $level + 1));
            }

            return $max;
        };

        return $walk($category->id, 1);
    }

    public function createProposal(array $data, int $userId): Category
    {
        return $this->create($data, $userId, Category::STATUS_PENDING, null);
    }

    public function createApproved(array $data, int $moderatorId): Category
    {
        return $this->create($data, $moderatorId, Category::STATUS_APPROVED, $moderatorId);
    }

    public function updateProposal(Category $category, array $data): void
    {
        $this->update($category, $data, Category::STATUS_PENDING);
    }

    public function updateCategory(Category $category, array $data): void
    {
        $this->update($category, $data, $category->status);
    }

    public function approve(Category $category, int $moderatorId): void
    {
        if (!$category->isPending()) {
            throw new DomainRuleException('Kategorie už není ve stavu čeká na schválení.');
        }
        if ($category->parent_id !== null) {
            $parent = $category->parent;
            if ($parent === null || !$parent->isApproved()) {
                throw new DomainRuleException('Rodičovská kategorie musí být schválená.');
            }
        }

        $category->forceFill([
            'status' => Category::STATUS_APPROVED,
            'reviewed_by' => $moderatorId,
            'reviewed_at' => utc_now(),
            'rejection_reason' => null,
        ])->save();
    }

    public function reject(Category $category, int $moderatorId, string $reason): void
    {
        if (!$category->isPending()) {
            throw new DomainRuleException('Kategorie už není ve stavu čeká na schválení.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new DomainRuleException('Důvod zamítnutí musí mít 3–255 znaků.');
        }

        $category->forceFill([
            'status' => Category::STATUS_REJECTED,
            'reviewed_by' => $moderatorId,
            'reviewed_at' => utc_now(),
            'rejection_reason' => $reason,
        ])->save();
    }

    public function delete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new DomainRuleException('Kategorii nelze smazat, protože má podkategorie.');
        }
        if ($category->events()->exists()) {
            throw new DomainRuleException('Kategorii nelze smazat, protože je použita u události.');
        }

        $category->delete();
    }

    /**
     * @param array<int, Category> $rows
     * @return Collection<int, Category>
     */
    private function buildTree(array|Collection $rows): Collection
    {
        $byId = [];
        foreach ($rows as $row) {
            $row->setRelation('children', collect());
            $byId[$row->id] = $row;
        }

        $roots = collect();
        foreach ($byId as $row) {
            $parent = $row->parent_id === null ? null : ($byId[$row->parent_id] ?? null);
            if ($parent === null) {
                $roots->push($row);
                continue;
            }
            $parent->children->push($row);
        }

        return $roots;
    }

    private function walkOptions(Collection $nodes, int $depth, array $excluded, array &$options): void
    {
        foreach ($nodes as $node) {
            if (in_array($node->id, $excluded, true)) {
                continue;
            }
            $options[] = [
                'id' => $node->id,
                'label' => str_repeat('— ', $depth - 1) . $node->name,
                'depth' => $depth,
            ];
            $this->walkOptions($node->children, $depth + 1, $excluded, $options);
        }
    }

    /**
     * @param array<int, array{model: Category, depth: int}> $flat
     */
    private function flattenRows(Collection $nodes, int $depth, array &$flat): void
    {
        foreach ($nodes as $node) {
            $flat[] = ['model' => $node, 'depth' => $depth];
            $this->flattenRows($node->children, $depth + 1, $flat);
        }
    }

    private function create(array $data, int $userId, string $status, ?int $reviewerId): Category
    {
        $name = trim((string) $data['name']);
        $parentId = $data['parent_id'] ?? null;

        $errors = $this->validatePosition($parentId === null ? null : (int) $parentId, null, $name);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return Category::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'parent_id' => $parentId === null ? null : (int) $parentId,
            'description' => $this->nullableString($data['description'] ?? null),
            'status' => $status,
            'proposed_by' => $userId,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => $reviewerId === null ? null : utc_now(),
        ]);
    }

    private function update(Category $category, array $data, string $status): void
    {
        $name = trim((string) $data['name']);
        $parentId = $data['parent_id'] === null ? null : (int) $data['parent_id'];
        $errors = $this->validatePosition($parentId, $category, $name);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $category->forceFill([
            'name' => $name,
            'slug' => $name === $category->name ? $category->slug : $this->uniqueSlug($name, $category->id),
            'parent_id' => $data['parent_id'] === null ? null : (int) $data['parent_id'],
            'description' => $this->nullableString($data['description'] ?? null),
            'status' => $status,
            'reviewed_by' => $status === Category::STATUS_PENDING ? null : $category->reviewed_by,
            'reviewed_at' => $status === Category::STATUS_PENDING ? null : $category->reviewed_at,
            'rejection_reason' => $status === Category::STATUS_PENDING ? null : $category->rejection_reason,
        ])->save();
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Slug::make($name, 100);
        $slug = $base;
        $counter = 1;

        while (Category::query()->where('slug', $slug)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists()) {
            $counter++;
            $suffix = '-' . $counter;
            $slug = mb_substr($base, 0, 100 - strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    private function nameExists(string $name, ?int $parentId, ?int $exceptId = null): bool
    {
        return Category::query()
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    private function nullableString(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
