<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
    <h1 class="h4 mb-0">Moderace komentářů</h1>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<form method="get" action="<?= e(url('/moderace/komentare')) ?>" class="bg-white border rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="stav" class="form-label">Stav</label>
            <select class="form-select" id="stav" name="stav">
                <option value="">Všechny</option>
                <option value="visible"<?= selected($status, 'visible') ?>>Zobrazené</option>
                <option value="hidden"<?= selected($status, 'hidden') ?>>Skryté</option>
            </select>
        </div>
        <div class="col-md-5">
            <label for="udalost" class="form-label">Událost</label>
            <select class="form-select" id="udalost" name="udalost">
                <option value="">Všechny</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= (int) $event['id'] ?>"<?= selected($eventId, $event['id']) ?>>
                        <?= e($event['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filtrovat</button>
        </div>
    </div>
</form>

<?php if ($comments === []): ?>
    <div class="alert alert-info">Žádné komentáře neodpovídají filtru.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($comments as $comment): ?>
            <?php
            $hidden = $comment['status'] === 'hidden';
            $badge = $hidden ? 'secondary' : 'success';
            ?>
            <div class="col-lg-6">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="fw-semibold"><?= e($comment['author_name']) ?></div>
                            <div class="small text-muted">
                                <?= e($comment['event_title']) ?> ·
                                <?= e(format_datetime($comment['created_at'])) ?>
                            </div>
                        </div>
                        <span class="badge text-bg-<?= $badge ?>"><?= $hidden ? 'Skrytý' : 'Zobrazený' ?></span>
                    </div>

                    <p class="mb-2"><?= nl2br(e($comment['body'])) ?></p>

                    <?php if ($hidden): ?>
                        <p class="small text-muted mb-2">
                            Skryl <?= e($comment['moderator_name'] ?? '—') ?>
                            <?= !empty($comment['moderated_at']) ? '· ' . e(format_datetime($comment['moderated_at'])) : '' ?>
                            <?php if (!empty($comment['hidden_reason'])): ?>
                                <br>Důvod: <?= e($comment['hidden_reason']) ?>
                            <?php endif; ?>
                        </p>
                        <form method="post" action="<?= e(url('/moderace/komentare/' . $comment['id'] . '/zobrazit')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-success">Zobrazit komentář</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/moderace/komentare/' . $comment['id'] . '/skryt')) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <label for="reason-<?= (int) $comment['id'] ?>" class="form-label small">
                                    Důvod skrytí <span aria-hidden="true">*</span>
                                </label>
                                <textarea class="form-control form-control-sm" id="reason-<?= (int) $comment['id'] ?>"
                                          name="reason" rows="2" required minlength="3" maxlength="255"></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Skrýt komentář</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
