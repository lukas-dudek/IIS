<?php

declare(strict_types=1);

$badge = match ($category['status']) {
    'approved' => 'success',
    'rejected' => 'danger',
    default => 'warning',
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <a href="<?= e(url('/moderace/kategorie')) ?>">← Zpět na moderaci kategorií</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('/moderace/kategorie/' . $category['id'] . '/upravit')) ?>">
        Upravit kategorii
    </a>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <h1 class="h3 mb-0"><?= e($category['name']) ?></h1>
                <span class="badge text-bg-<?= $badge ?>"><?= e(moderation_status_label($category['status'])) ?></span>
            </div>

            <?php if (!empty($category['description'])): ?>
                <p class="mb-3"><?= nl2br(e($category['description'])) ?></p>
            <?php endif; ?>

            <dl class="row small mb-0">
                <dt class="col-sm-4">Rodič</dt>
                <dd class="col-sm-8"><?= e($category['parent_name'] ?? '—') ?></dd>
                <dt class="col-sm-4">Slug</dt>
                <dd class="col-sm-8"><code><?= e($category['slug']) ?></code></dd>
                <dt class="col-sm-4">Navrhl</dt>
                <dd class="col-sm-8"><?= e($category['proposer_name']) ?> (<?= e($category['proposer_username']) ?>)</dd>
                <dt class="col-sm-4">Vytvořeno</dt>
                <dd class="col-sm-8"><?= e(format_datetime($category['created_at']) ?: '—') ?></dd>
                <?php if (!empty($category['reviewer_name'])): ?>
                    <dt class="col-sm-4">Naposledy posoudil</dt>
                    <dd class="col-sm-8"><?= e($category['reviewer_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($category['reviewed_at'])): ?>
                    <dt class="col-sm-4">Datum posouzení</dt>
                    <dd class="col-sm-8"><?= e(format_datetime($category['reviewed_at'])) ?></dd>
                <?php endif; ?>
                <?php if (!empty($category['rejection_reason'])): ?>
                    <dt class="col-sm-4">Důvod zamítnutí</dt>
                    <dd class="col-sm-8"><?= e($category['rejection_reason']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($category['status'] === 'pending'): ?>
            <div class="bg-white border rounded-3 p-4 mt-4">
                <h2 class="h5 mb-3">Schválení nebo zamítnutí</h2>

                <form method="post" action="<?= e(url('/moderace/kategorie/' . $category['id'] . '/schvalit')) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">Schválit kategorii</button>
                </form>

                <form method="post" action="<?= e(url('/moderace/kategorie/' . $category['id'] . '/zamitnout')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label for="reason" class="form-label">
                            Důvod zamítnutí <span aria-hidden="true">*</span>
                        </label>
                        <textarea class="form-control<?= isset($reasonErrors['reason']) ? ' is-invalid' : '' ?>"
                                  id="reason" name="reason" rows="3" required minlength="3" maxlength="255"
                                  <?= isset($reasonErrors['reason']) ? 'aria-invalid="true" aria-describedby="reason-error"' : '' ?>><?= e($reason) ?></textarea>
                        <?php if (isset($reasonErrors['reason'])): ?>
                            <div class="invalid-feedback d-block" id="reason-error"><?= e($reasonErrors['reason']) ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-outline-danger">Zamítnout kategorii</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h6 mb-3">Smazání kategorie</h2>
            <p class="text-muted small">
                Smazat lze jen kategorii bez potomků a bez událostí.
            </p>
            <form method="post" action="<?= e(url('/moderace/kategorie/' . $category['id'] . '/smazat')) ?>"
                  data-confirm="Opravdu smazat kategorii <?= e($category['name']) ?>?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">Smazat kategorii</button>
            </form>
        </div>
    </div>
</div>
