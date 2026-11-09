<?php

declare(strict_types=1);

$firstError = array_key_first($errors);
$currentParent = old('parent_id', $category['parent_id'] ?? '');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0"><?= e($heading) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Zpět</a>
</div>

<div class="bg-white border rounded-3 p-4">
    <?php require __DIR__ . '/../partials/form-errors.php'; ?>

    <form method="post" action="<?= e($action) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="name" class="form-label">
                Název kategorie <span aria-hidden="true">*</span>
            </label>
            <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                   id="name" name="name" value="<?= e(old('name', $category['name'] ?? '')) ?>"
                   required <?= $firstError === 'name' ? 'autofocus' : '' ?>
                   <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
            <div class="form-text">2–80 znaků.</div>
            <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback d-block" id="name-error"><?= e($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="parent_id" class="form-label">Rodičovská kategorie (nepovinné)</label>
            <select class="form-select<?= isset($errors['parent_id']) ? ' is-invalid' : '' ?>"
                    id="parent_id" name="parent_id"
                    <?= $firstError === 'parent_id' ? 'autofocus' : '' ?>
                    <?= isset($errors['parent_id']) ? 'aria-invalid="true" aria-describedby="parent-error"' : '' ?>>
                <option value="">— žádná (kořen stromu) —</option>
                <?php foreach ($parentOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"<?= selected($currentParent, $option['id']) ?>>
                        <?= str_repeat('— ', max(0, (int) $option['depth'] - 1)) . e($option['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Rodič musí být schválená kategorie; maximální hloubka stromu je 4.</div>
            <?php if (isset($errors['parent_id'])): ?>
                <div class="invalid-feedback d-block" id="parent-error"><?= e($errors['parent_id']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Popis (nepovinné)</label>
            <textarea class="form-control<?= isset($errors['description']) ? ' is-invalid' : '' ?>"
                      id="description" name="description" rows="4"
                      <?= $firstError === 'description' ? 'autofocus' : '' ?>
                      <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="description-error"' : '' ?>><?= e(old('description', $category['description'] ?? '')) ?></textarea>
            <div class="form-text">Maximálně 2000 znaků.</div>
            <?php if (isset($errors['description'])): ?>
                <div class="invalid-feedback d-block" id="description-error"><?= e($errors['description']) ?></div>
            <?php endif; ?>
        </div>

        <p class="text-muted small">* povinné pole</p>
        <?php if (!empty($approvalNote)): ?>
            <p class="text-muted small">Návrh se veřejně zobrazí až po schválení moderátorem.</p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
    </form>
</div>
