<?php

declare(strict_types=1);

$isEdit = $mode === 'edit';
$targetId = (int) ($user['id'] ?? 0);
$firstError = array_key_first($errors);
$currentRole = old('role', $user['role'] ?? 'user');
$action = $isEdit
    ? url('/admin/uzivatele/' . $targetId . '/upravit')
    : url('/admin/uzivatele/novy');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0"><?= $isEdit ? 'Upravit uživatele' : 'Nový uživatel' ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/uzivatele')) ?>">Zpět na seznam</a>
</div>

<div class="bg-white border rounded-3 p-4">
    <?php require __DIR__ . '/../../partials/form-errors.php'; ?>

    <form method="post" action="<?= e($action) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="username" class="form-label">
                Uživatelské jméno <?= $isEdit ? '' : '<span aria-hidden="true">*</span>' ?>
            </label>
            <?php if ($isEdit): ?>
                <input type="text" class="form-control" id="username" value="<?= e($user['username']) ?>" readonly>
                <div class="form-text">Uživatelské jméno nelze měnit.</div>
            <?php else: ?>
                <input type="text" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>"
                       id="username" name="username" value="<?= e(old('username')) ?>"
                       autocomplete="off" required
                       <?= $firstError === 'username' ? 'autofocus' : '' ?>
                       <?= isset($errors['username']) ? 'aria-invalid="true" aria-describedby="username-error"' : '' ?>>
                <div class="form-text">3–50 znaků, povolena jsou písmena, čísla, tečka, podtržítko a pomlčka.</div>
                <?php if (isset($errors['username'])): ?>
                    <div class="invalid-feedback" id="username-error"><?= e($errors['username']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">
                Jméno <span aria-hidden="true">*</span>
            </label>
            <input type="text" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
                   id="name" name="name" value="<?= e(old('name', $user['name'] ?? '')) ?>"
                   autocomplete="name" required
                   <?= $firstError === 'name' ? 'autofocus' : '' ?>
                   <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
            <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback" id="name-error"><?= e($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">
                E-mail <span aria-hidden="true">*</span>
            </label>
            <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                   id="email" name="email" value="<?= e(old('email', $user['email'] ?? '')) ?>"
                   autocomplete="email" required
                   <?= $firstError === 'email' ? 'autofocus' : '' ?>
                   <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback" id="email-error"><?= e($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="role" class="form-label">
                Role <span aria-hidden="true">*</span>
            </label>
            <select class="form-select<?= isset($errors['role']) ? ' is-invalid' : '' ?>" id="role" name="role" required
                    <?= $firstError === 'role' ? 'autofocus' : '' ?>
                    <?= isset($errors['role']) ? 'aria-invalid="true" aria-describedby="role-error"' : '' ?>>
                <option value="user"<?= selected($currentRole, 'user') ?>>Uživatel</option>
                <option value="moderator"<?= selected($currentRole, 'moderator') ?>>Moderátor</option>
                <option value="admin"<?= selected($currentRole, 'admin') ?>>Administrátor</option>
            </select>
            <?php if (isset($errors['role'])): ?>
                <div class="invalid-feedback" id="role-error"><?= e($errors['role']) ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$isEdit): ?>
            <div class="mb-3">
                <label for="password" class="form-label">
                    Počáteční heslo <span aria-hidden="true">*</span>
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
        <?php endif; ?>

        <p class="text-muted small">* povinné pole</p>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Uložit' : 'Vytvořit uživatele' ?></button>
    </form>
</div>

<?php if ($isEdit): ?>
    <div class="bg-white border rounded-3 p-4 mt-4">
        <h2 class="h5 mb-3">Reset hesla</h2>

        <?php
        $errors = $passwordErrors;
        $formError = null;
        $passwordFirstError = array_key_first($passwordErrors);
        ?>
        <?php require __DIR__ . '/../../partials/form-errors.php'; ?>

        <form method="post" action="<?= e(url('/admin/uzivatele/' . $targetId . '/heslo')) ?>"
              data-confirm="Opravdu resetovat heslo uživatele <?= e($user['username']) ?>?" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="new_password" class="form-label">
                    Nové heslo <span aria-hidden="true">*</span>
                </label>
                <input type="password" class="form-control<?= isset($passwordErrors['new_password']) ? ' is-invalid' : '' ?>"
                       id="new_password" name="new_password" autocomplete="new-password" required
                       <?= $passwordFirstError === 'new_password' ? 'autofocus' : '' ?>
                       <?= isset($passwordErrors['new_password']) ? 'aria-invalid="true" aria-describedby="new-password-error"' : '' ?>>
                <div class="form-text">Alespoň 8 znaků. Změna ukončí všechny relace uživatele.</div>
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
            <button type="submit" class="btn btn-outline-danger">Resetovat heslo</button>
        </form>
    </div>
<?php endif; ?>
