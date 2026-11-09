<?php

declare(strict_types=1);

$firstError = array_key_first($errors);
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="bg-white border rounded-3 p-4">
            <h1 class="h4 mb-3">Registrace</h1>
            <?php require __DIR__ . '/../partials/form-errors.php'; ?>

            <form method="post" action="<?= e(url('/registrace')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="username" class="form-label">
                        Uživatelské jméno <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                           id="username" name="username" value="<?= e(old('username')) ?>"
                           autocomplete="username" required
                           <?= $firstError === 'username' ? 'autofocus' : '' ?>
                           <?= isset($errors['username']) ? 'aria-invalid="true" aria-describedby="username-error"' : '' ?>>
                    <div class="form-text">3–50 znaků, povolena jsou písmena, čísla, tečka, podtržítko a pomlčka.</div>
                    <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback" id="username-error"><?= e($errors['username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">
                        E-mail <span aria-hidden="true">*</span>
                    </label>
                    <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                           id="email" name="email" value="<?= e(old('email')) ?>"
                           autocomplete="email" required
                           <?= $firstError === 'email' ? 'autofocus' : '' ?>
                           <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback" id="email-error"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">
                        Jméno <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                           id="name" name="name" value="<?= e(old('name')) ?>"
                           autocomplete="name" required
                           <?= $firstError === 'name' ? 'autofocus' : '' ?>
                           <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback" id="name-error"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        Heslo <span aria-hidden="true">*</span>
                    </label>
                    <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                           id="password" name="password" autocomplete="new-password" required
                           <?= $firstError === 'password' ? 'autofocus' : '' ?>
                           <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                    <div class="form-text">Alespoň 8 znaků.</div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback" id="password-error"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="password_confirm" class="form-label">
                        Potvrzení hesla <span aria-hidden="true">*</span>
                    </label>
                    <input type="password" class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>"
                           id="password_confirm" name="password_confirm" autocomplete="new-password" required
                           <?= $firstError === 'password_confirm' ? 'autofocus' : '' ?>
                           <?= isset($errors['password_confirm']) ? 'aria-invalid="true" aria-describedby="password-confirm-error"' : '' ?>>
                    <?php if (isset($errors['password_confirm'])): ?>
                        <div class="invalid-feedback" id="password-confirm-error"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>

                <p class="text-muted small">* povinné pole</p>

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" class="btn btn-primary">Vytvořit účet</button>
                    <a href="<?= e(url('/prihlaseni')) ?>">Již mám účet</a>
                </div>
            </form>
        </div>
    </div>
</div>
