<?php

declare(strict_types=1);

$homePath = rtrim(url('/'), '/');
$current = rtrim($currentPath, '/');
$isHome = $current === $homePath;
$isLogin = $current === rtrim(url('/prihlaseni'), '/');
$isRegister = $current === rtrim(url('/registrace'), '/');
$isProfile = str_starts_with($current . '/', rtrim(url('/profil'), '/') . '/');
$isAdminUsers = str_starts_with($current . '/', rtrim(url('/admin/uzivatele'), '/') . '/');
$isVenues = $current === rtrim(url('/mista'), '/')
    || str_starts_with($current . '/', rtrim(url('/mista'), '/') . '/');
$isEvents = $current === rtrim(url('/udalosti'), '/')
    || (str_starts_with($current . '/', rtrim(url('/udalosti'), '/') . '/')
        && !str_contains($current, '/upravit')
        && !str_ends_with($current, '/nova'));
$isProposals = $current === rtrim(url('/moje-navrhy'), '/');
$isMyEvents = $current === rtrim(url('/moje-udalosti'), '/');
$isMyRegistrations = $current === rtrim(url('/moje-registrace'), '/');
$isCalendar = $current === rtrim(url('/muj-kalendar'), '/');
$isModeration = str_starts_with($current . '/', rtrim(url('/moderace'), '/') . '/');
$isModerator = $user !== null && in_array($user['role'], ['moderator', 'admin'], true);
$isAdmin = $user !== null && $user['role'] === 'admin';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavigation"
                aria-controls="mainNavigation" aria-expanded="false" aria-label="Přepnout navigaci">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?= $isHome ? ' active' : '' ?>" href="<?= e(url('/')) ?>"
                       <?= $isHome ? 'aria-current="page"' : '' ?>>Domů</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $isEvents ? ' active' : '' ?>" href="<?= e(url('/udalosti')) ?>"
                       <?= $isEvents ? 'aria-current="page"' : '' ?>>Události</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $isVenues ? ' active' : '' ?>" href="<?= e(url('/mista')) ?>"
                       <?= $isVenues ? 'aria-current="page"' : '' ?>>Místa</a>
                </li>
                <?php if ($isModerator): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $isModeration ? ' active' : '' ?>"
                           href="<?= e(url('/moderace/mista')) ?>"
                           <?= $isModeration ? 'aria-current="page"' : '' ?>>Moderace</a>
                    </li>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $isAdminUsers ? ' active' : '' ?>" href="<?= e(url('/admin/uzivatele')) ?>"
                           <?= $isAdminUsers ? 'aria-current="page"' : '' ?>>Administrace</a>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($user === null): ?>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link<?= $isLogin ? ' active' : '' ?>" href="<?= e(url('/prihlaseni')) ?>"
                           <?= $isLogin ? 'aria-current="page"' : '' ?>>Přihlásit se</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isRegister ? ' active' : '' ?>" href="<?= e(url('/registrace')) ?>"
                           <?= $isRegister ? 'aria-current="page"' : '' ?>>Registrovat se</a>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link<?= $isCalendar ? ' active' : '' ?>" href="<?= e(url('/muj-kalendar')) ?>"
                           <?= $isCalendar ? 'aria-current="page"' : '' ?>>Můj kalendář</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isMyRegistrations ? ' active' : '' ?>" href="<?= e(url('/moje-registrace')) ?>"
                           <?= $isMyRegistrations ? 'aria-current="page"' : '' ?>>Moje registrace</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isMyEvents ? ' active' : '' ?>" href="<?= e(url('/moje-udalosti')) ?>"
                           <?= $isMyEvents ? 'aria-current="page"' : '' ?>>Moje události</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isProposals ? ' active' : '' ?>" href="<?= e(url('/moje-navrhy')) ?>"
                           <?= $isProposals ? 'aria-current="page"' : '' ?>>Moje návrhy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $isProfile ? ' active' : '' ?>" href="<?= e(url('/profil')) ?>"
                           <?= $isProfile ? 'aria-current="page"' : '' ?>><?= e($user['name']) ?></a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?= e(url('/odhlaseni')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-link nav-link p-0">Odhlásit se</button>
                        </form>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
