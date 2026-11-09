<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Uživatelé</h1>
    <a class="btn btn-primary" href="<?= e(url('/admin/uzivatele/novy')) ?>">Nový uživatel</a>
</div>

<form method="get" action="<?= e(url('/admin/uzivatele')) ?>" class="bg-white border rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label for="q" class="form-label">Hledat</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Jméno, uživatelské jméno nebo e-mail">
        </div>
        <div class="col-md-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">Všechny</option>
                <option value="admin"<?= selected($role, 'admin') ?>>Administrátor</option>
                <option value="moderator"<?= selected($role, 'moderator') ?>>Moderátor</option>
                <option value="user"<?= selected($role, 'user') ?>>Uživatel</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="active" class="form-label">Stav</label>
            <select class="form-select" id="active" name="active">
                <option value="">Všechny</option>
                <option value="1"<?= selected($active, '1') ?>>Aktivní</option>
                <option value="0"<?= selected($active, '0') ?>>Neaktivní</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary w-100">Filtrovat</button>
            <?php if ($q !== '' || $role !== '' || $active !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('/admin/uzivatele')) ?>">Zrušit</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($users === []): ?>
    <div class="alert alert-info">
        Žádní uživatelé neodpovídají filtru.
        <a href="<?= e(url('/admin/uzivatele')) ?>">Zrušit filtry</a>
    </div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Uživatel</th>
                <th scope="col">E-mail</th>
                <th scope="col">Role</th>
                <th scope="col">Stav</th>
                <th scope="col">Poslední přihlášení</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $item): ?>
                <?php
                $isSelf = (int) $item['id'] === $currentUserId;
                $isActive = (int) $item['is_active'] === 1;
                $isLastAdmin = $item['role'] === 'admin' && $isActive && $activeAdmins <= 1;
                ?>
                <tr>
                    <td>
                        <strong><?= e($item['username']) ?></strong>
                        <div class="small text-muted"><?= e($item['name']) ?></div>
                    </td>
                    <td><?= e($item['email']) ?></td>
                    <td><?= e(role_label($item['role'])) ?></td>
                    <td>
                        <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
                            <?= $isActive ? 'Aktivní' : 'Neaktivní' ?>
                        </span>
                    </td>
                    <td><?= e(format_datetime($item['last_login_at']) ?: '—') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('/admin/uzivatele/' . $item['id'] . '/upravit')) ?>">Upravit</a>

                        <?php if (!$isSelf): ?>
                            <?php if ($isActive): ?>
                                <form method="post" class="d-inline"
                                      action="<?= e(url('/admin/uzivatele/' . $item['id'] . '/deaktivovat')) ?>"
                                      data-confirm="Opravdu deaktivovat uživatele <?= e($item['username']) ?>?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                            <?= $isLastAdmin ? 'disabled title="Posledního aktivního administrátora nelze deaktivovat."' : '' ?>>
                                        Deaktivovat
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="d-inline"
                                      action="<?= e(url('/admin/uzivatele/' . $item['id'] . '/aktivovat')) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Aktivovat</button>
                                </form>
                            <?php endif; ?>

                            <form method="post" class="d-inline"
                                  action="<?= e(url('/admin/uzivatele/' . $item['id'] . '/smazat')) ?>"
                                  data-confirm="Opravdu smazat uživatele <?= e($item['username']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        <?= $isLastAdmin ? 'disabled title="Posledního aktivního administrátora nelze smazat."' : '' ?>>
                                    Smazat
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
