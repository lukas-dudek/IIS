<?php

declare(strict_types=1);
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="bg-white border rounded-3 p-4">
            <h1 class="h4 mb-3">Přihlášení</h1>
            <?php require __DIR__ . '/../partials/form-errors.php'; ?>

            <form method="post" action="<?= e(url('/prihlaseni')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="return" value="<?= e($return) ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">
                        Uživatelské jméno nebo e-mail <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                           id="username" name="username" value="<?= e(old('username')) ?>"
                           autocomplete="username" required autofocus
                           <?= isset($errors['username']) ? 'aria-invalid="true" aria-describedby="username-error"' : '' ?>>
                    <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback d-block" id="username-error"><?= e($errors['username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        Heslo <span aria-hidden="true">*</span>
                    </label>
                    <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                           id="password" name="password" autocomplete="current-password" required
                           <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback d-block" id="password-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <p class="text-muted small">* povinné pole</p>

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" class="btn btn-primary">Přihlásit se</button>
                    <a href="<?= e(url('/registrace')) ?>">Vytvořit účet</a>
                </div>
            </form>
        </div>
    </div>
</div>
