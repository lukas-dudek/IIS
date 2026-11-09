<?php

declare(strict_types=1);

$badge = match ($venue['status']) {
    'approved' => 'success',
    'rejected' => 'danger',
    default => 'warning',
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <a href="<?= e(url('/moderace/mista')) ?>">← Zpět na moderaci míst</a>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/moderace/mista/' . $venue['id'] . '/upravit')) ?>">
        Upravit místo
    </a>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <h1 class="h3 mb-0"><?= e($venue['name']) ?></h1>
                <span class="badge text-bg-<?= $badge ?>"><?= e(moderation_status_label($venue['status'])) ?></span>
            </div>

            <p class="mb-3">
                <?= e($venue['street']) ?><br>
                <?= e(trim(($venue['postal_code'] ?? '') . ' ' . $venue['city'])) ?>
            </p>

            <?php if (!empty($venue['description'])): ?>
                <p class="mb-3"><?= nl2br(e($venue['description'])) ?></p>
            <?php endif; ?>

            <dl class="row small mb-0">
                <dt class="col-sm-4">Navrhl</dt>
                <dd class="col-sm-8"><?= e($venue['proposer_name']) ?> (<?= e($venue['proposer_username']) ?>)</dd>
                <dt class="col-sm-4">Vytvořeno</dt>
                <dd class="col-sm-8"><?= e(format_datetime($venue['created_at']) ?: '—') ?></dd>
                <?php if (!empty($venue['reviewer_name'])): ?>
                    <dt class="col-sm-4">Naposledy posoudil</dt>
                    <dd class="col-sm-8"><?= e($venue['reviewer_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($venue['reviewed_at'])): ?>
                    <dt class="col-sm-4">Datum posouzení</dt>
                    <dd class="col-sm-8"><?= e(format_datetime($venue['reviewed_at'])) ?></dd>
                <?php endif; ?>
                <?php if (!empty($venue['rejection_reason'])): ?>
                    <dt class="col-sm-4">Důvod zamítnutí</dt>
                    <dd class="col-sm-8"><?= e($venue['rejection_reason']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($venue['status'] === 'pending'): ?>
            <div class="bg-white border rounded-3 p-4 mt-4">
                <h2 class="h5 mb-3">Schválení nebo zamítnutí</h2>

                <form method="post" action="<?= e(url('/moderace/mista/' . $venue['id'] . '/schvalit')) ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">Schválit místo</button>
                </form>

                <form method="post" action="<?= e(url('/moderace/mista/' . $venue['id'] . '/zamitnout')) ?>" novalidate>
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
                    <button type="submit" class="btn btn-outline-danger">Zamítnout místo</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h6 mb-3">Smazání místa</h2>
            <p class="text-muted small">
                Smazat lze jen místo, které není použité v žádné události.
            </p>
            <form method="post" action="<?= e(url('/moderace/mista/' . $venue['id'] . '/smazat')) ?>"
                  data-confirm="Opravdu smazat místo <?= e($venue['name']) ?>?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger">Smazat místo</button>
            </form>
        </div>
    </div>

    <?php if (!empty($venue['photo_path'])): ?>
        <div class="col-lg-4">
            <img src="<?= e(url('/uploads/' . $venue['photo_path'])) ?>"
                 alt="<?= e($venue['name']) ?>" class="img-fluid rounded-3 border">
        </div>
    <?php endif; ?>
</div>
