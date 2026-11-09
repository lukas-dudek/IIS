<?php

declare(strict_types=1);

$firstError = array_key_first($errors);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0"><?= e($heading) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">Zpět</a>
</div>

<div class="bg-white border rounded-3 p-4">
    <?php require __DIR__ . '/../partials/form-errors.php'; ?>

    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="name" class="form-label">
                Název místa <span aria-hidden="true">*</span>
            </label>
            <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                   id="name" name="name" value="<?= e(old('name', $venue['name'] ?? '')) ?>"
                   required <?= $firstError === 'name' ? 'autofocus' : '' ?>
                   <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
            <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback d-block" id="name-error"><?= e($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="street" class="form-label">
                Ulice a číslo <span aria-hidden="true">*</span>
            </label>
            <input type="text" class="form-control<?= isset($errors['street']) ? ' is-invalid' : '' ?>"
                   id="street" name="street" value="<?= e(old('street', $venue['street'] ?? '')) ?>"
                   required <?= $firstError === 'street' ? 'autofocus' : '' ?>
                   <?= isset($errors['street']) ? 'aria-invalid="true" aria-describedby="street-error"' : '' ?>>
            <?php if (isset($errors['street'])): ?>
                <div class="invalid-feedback d-block" id="street-error"><?= e($errors['street']) ?></div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="city" class="form-label">
                    Město <span aria-hidden="true">*</span>
                </label>
                <input type="text" class="form-control<?= isset($errors['city']) ? ' is-invalid' : '' ?>"
                       id="city" name="city" value="<?= e(old('city', $venue['city'] ?? '')) ?>"
                       required <?= $firstError === 'city' ? 'autofocus' : '' ?>
                       <?= isset($errors['city']) ? 'aria-invalid="true" aria-describedby="city-error"' : '' ?>>
                <?php if (isset($errors['city'])): ?>
                    <div class="invalid-feedback d-block" id="city-error"><?= e($errors['city']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-5 mb-3">
                <label for="postal_code" class="form-label">PSČ (nepovinné)</label>
                <input type="text" class="form-control<?= isset($errors['postal_code']) ? ' is-invalid' : '' ?>"
                       id="postal_code" name="postal_code" value="<?= e(old('postal_code', $venue['postal_code'] ?? '')) ?>"
                       inputmode="numeric"
                       <?= $firstError === 'postal_code' ? 'autofocus' : '' ?>
                       <?= isset($errors['postal_code']) ? 'aria-invalid="true" aria-describedby="postal-code-error"' : '' ?>>
                <div class="form-text">Například 123 45.</div>
                <?php if (isset($errors['postal_code'])): ?>
                    <div class="invalid-feedback d-block" id="postal-code-error"><?= e($errors['postal_code']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Popis (nepovinné)</label>
            <textarea class="form-control<?= isset($errors['description']) ? ' is-invalid' : '' ?>"
                      id="description" name="description" rows="4"
                      <?= $firstError === 'description' ? 'autofocus' : '' ?>
                      <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="description-error"' : '' ?>><?= e(old('description', $venue['description'] ?? '')) ?></textarea>
            <div class="form-text">Maximálně 2000 znaků.</div>
            <?php if (isset($errors['description'])): ?>
                <div class="invalid-feedback d-block" id="description-error"><?= e($errors['description']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="photo" class="form-label">Fotka (nepovinné)</label>
            <input type="file" class="form-control<?= isset($errors['photo']) ? ' is-invalid' : '' ?>"
                   id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
                   <?= isset($errors['photo']) ? 'aria-invalid="true" aria-describedby="photo-error"' : '' ?>>
            <div class="form-text">JPEG, PNG nebo WebP, maximálně 2 MB.</div>
            <?php if (isset($errors['photo'])): ?>
                <div class="invalid-feedback d-block" id="photo-error"><?= e($errors['photo']) ?></div>
            <?php endif; ?>
            <?php if (!empty($venue['photo_path'])): ?>
                <div class="mt-2">
                    <img src="<?= e(url('/uploads/' . $venue['photo_path'])) ?>"
                         alt="Současná fotka místa <?= e($venue['name']) ?>"
                         class="img-thumbnail venue-preview">
                    <div class="form-text">Nahráním nové fotky současnou nahradíte.</div>
                </div>
            <?php endif; ?>
        </div>

        <p class="text-muted small">* povinné pole</p>
        <?php if (!empty($approvalNote)): ?>
            <p class="text-muted small">Návrh se veřejně zobrazí až po schválení moderátorem.</p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
    </form>
</div>
