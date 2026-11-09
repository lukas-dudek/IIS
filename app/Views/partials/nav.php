<?php

declare(strict_types=1);

$homePath = rtrim(url('/'), '/');
$current = rtrim($currentPath, '/');
$isHome = $current === $homePath;
$isLogin = $current === rtrim(url('/prihlaseni'), '/');
$isRegister = $current === rtrim(url('/registrace'), '/');
$isProfile = str_starts_with($current . '/', rtrim(url('/profil'), '/') . '/');
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
