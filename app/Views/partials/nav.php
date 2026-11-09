<?php

declare(strict_types=1);

$homePath = rtrim(url('/'), '/');
$current = rtrim($currentPath, '/');
$isHome = $current === $homePath;
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
            <?php if ($user !== null): ?>
                <span class="navbar-text"><?= e($user['name']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</nav>
