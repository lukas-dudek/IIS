<?php

declare(strict_types=1);

$profileFirstError = array_key_first($profileErrors);
$passwordFirstError = array_key_first($passwordErrors);
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="bg-white border rounded-3 p-4">
            <h1 class="h4 mb-3">Profil</h1>

            <?php $errors = $profileErrors; $formError = null; ?>
            <?php require __DIR__ . '/../partials/form-errors.php'; ?>

            <form method="post" action="<?= e(url('/profil')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label">
                        Jméno <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" class="form-control<?= isset($profileErrors['name']) ? ' is-invalid' : '' ?>"
                           id="name" name="name" value="<?= e(old('name', $user['name'])) ?>"
                           autocomplete="name" required
                           <?= $profileFirstError === 'name' ? 'autofocus' : '' ?>
                           <?= isset($profileErrors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
                    <?php if (isset($profileErrors['name'])): ?>
                        <div class="invalid-feedback" id="name-error"><?= e($profileErrors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">
                        E-mail <span aria-hidden="true">*</span>
                    </label>
                    <input type="email" class="form-control<?= isset($profileErrors['email']) ? ' is-invalid' : '' ?>"
                           id="email" name="email" value="<?= e(old('email', $user['email'])) ?>"
                           autocomplete="email" required
                           <?= $profileFirstError === 'email' ? 'autofocus' : '' ?>
                           <?= isset($profileErrors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                    <?php if (isset($profileErrors['email'])): ?>
                        <div class="invalid-feedback" id="email-error"><?= e($profileErrors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <p class="text-muted small">* povinné pole</p>
                <button type="submit" class="btn btn-primary">Uložit</button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="bg-white border rounded-3 p-4">
            <h2 class="h5 mb-3">Změna hesla</h2>

            <?php $errors = $passwordErrors; $formError = null; ?>
            <?php require __DIR__ . '/../partials/form-errors.php'; ?>

            <form method="post" action="<?= e(url('/profil/heslo')) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="current_password" class="form-label">
                        Současné heslo <span aria-hidden="true">*</span>
                    </label>
                    <input type="password" class="form-control<?= isset($passwordErrors['current_password']) ? ' is-invalid' : '' ?>"
                           id="current_password" name="current_password" autocomplete="current-password" required
                           <?= $passwordFirstError === 'current_password' ? 'autofocus' : '' ?>
                           <?= isset($passwordErrors['current_password']) ? 'aria-invalid="true" aria-describedby="current-password-error"' : '' ?>>
                    <?php if (isset($passwordErrors['current_password'])): ?>
                        <div class="invalid-feedback" id="current-password-error"><?= e($passwordErrors['current_password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">
                        Nové heslo <span aria-hidden="true">*</span>
                    </label>
                    <input type="password" class="form-control<?= isset($passwordErrors['new_password']) ? ' is-invalid' : '' ?>"
                           id="new_password" name="new_password" autocomplete="new-password" required
                           <?= $passwordFirstError === 'new_password' ? 'autofocus' : '' ?>
                           <?= isset($passwordErrors['new_password']) ? 'aria-invalid="true" aria-describedby="new-password-error"' : '' ?>>
                    <div class="form-text">Alespoň 8 znaků.</div>
                    <?php if (isset($passwordErrors['new_password'])): ?>
                        <div class="invalid-feedback" id="new-password-error"><?= e($passwordErrors['new_password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="new_password_confirm" class="form-label">
                        Potvrzení nového hesla <span aria-hidden="true">*</span>
                    </label>
                    <input type="password" class="form-control<?= isset($passwordErrors['new_password_confirm']) ? ' is-invalid' : '' ?>"
                           id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required
                           <?= $passwordFirstError === 'new_password_confirm' ? 'autofocus' : '' ?>
                           <?= isset($passwordErrors['new_password_confirm']) ? 'aria-invalid="true" aria-describedby="new-password-confirm-error"' : '' ?>>
                    <?php if (isset($passwordErrors['new_password_confirm'])): ?>
                        <div class="invalid-feedback" id="new-password-confirm-error"><?= e($passwordErrors['new_password_confirm']) ?></div>
                    <?php endif; ?>
                </div>

                <p class="text-muted small">* povinné pole</p>
                <button type="submit" class="btn btn-primary">Změnit heslo</button>
            </form>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h6 mb-3">Účet</h2>
            <dl class="row mb-0 small">
                <dt class="col-5">Uživatelské jméno</dt>
                <dd class="col-7"><?= e($user['username']) ?></dd>
                <dt class="col-5">Role</dt>
                <dd class="col-7"><?= e(role_label($user['role'])) ?></dd>
                <dt class="col-5">Poslední přihlášení</dt>
                <dd class="col-7"><?= e(format_datetime($user['last_login_at']) ?: '—') ?></dd>
            </dl>
        </div>
    </div>
</div>
