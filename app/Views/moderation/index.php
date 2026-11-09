<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
    <h1 class="h4 mb-0">Moderace</h1>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="row g-3">
    <div class="col-sm-6 col-xl-3">
        <a class="text-decoration-none" href="<?= e(url('/moderace/udalosti')) ?>">
            <div class="bg-white border rounded-3 p-4 h-100">
                <div class="text-muted small mb-1">Události čekající na schválení</div>
                <div class="display-6"><?= (int) $pendingEventsCount ?></div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="text-decoration-none" href="<?= e(url('/moderace/mista')) ?>">
            <div class="bg-white border rounded-3 p-4 h-100">
                <div class="text-muted small mb-1">Místa čekající na schválení</div>
                <div class="display-6"><?= (int) $pendingVenuesCount ?></div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="text-decoration-none" href="<?= e(url('/moderace/kategorie')) ?>">
            <div class="bg-white border rounded-3 p-4 h-100">
                <div class="text-muted small mb-1">Kategorie čekající na schválení</div>
                <div class="display-6"><?= (int) $pendingCategoriesCount ?></div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="text-decoration-none" href="<?= e(url('/moderace/komentare') . '?stav=hidden') ?>">
            <div class="bg-white border rounded-3 p-4 h-100">
                <div class="text-muted small mb-1">Skryté komentáře</div>
                <div class="display-6"><?= (int) $hiddenCommentsCount ?></div>
            </div>
        </a>
    </div>
</div>
