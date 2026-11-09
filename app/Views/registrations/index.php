<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Moje registrace</h1>
    <a class="btn btn-outline-primary" href="<?= e(url('/udalosti')) ?>">Procházet události</a>
</div>

<?php if ($registrations === []): ?>
    <div class="alert alert-info">
        Zatím nemáte žádné registrace.
        <a href="<?= e(url('/udalosti')) ?>">Vyberte si událost</a>.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($registrations as $item): ?>
            <?php
            $badge = match ($item['status']) {
                'confirmed' => 'success',
                'pending' => 'warning',
                default => 'secondary',
            };
            $active = in_array($item['status'], ['pending', 'confirmed'], true);
            $canCancel = $active && !is_past($item['starts_at']);
            ?>
            <div class="col-lg-6">
                <div class="bg-white border rounded-3 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <h2 class="h5 mb-0">
                            <a class="text-decoration-none" href="<?= e(url('/udalosti/' . $item['slug'])) ?>">
                                <?= e($item['title']) ?>
                            </a>
                        </h2>
                        <span class="badge text-bg-<?= $badge ?>"><?= e(registration_status_label($item['status'])) ?></span>
                    </div>

                    <p class="text-muted small mb-3">
                        <?= e(format_date_range($item['starts_at'], $item['ends_at'])) ?><br>
                        <?= e($item['venue_name']) ?>, <?= e($item['venue_city']) ?>
                    </p>

                    <dl class="row small mb-3">
                        <dt class="col-5">Typ vstupenky</dt>
                        <dd class="col-7"><?= e($item['ticket_name']) ?></dd>
                        <dt class="col-5">Cena</dt>
                        <dd class="col-7"><?= e(format_money($item['price'])) ?></dd>
                        <?php if ($item['status'] === 'pending'): ?>
                            <dt class="col-5">Variabilní symbol</dt>
                            <dd class="col-7"><code><?= e($item['variable_symbol'] ?? '—') ?></code></dd>
                        <?php endif; ?>
                    </dl>

                    <?php if ($item['status'] === 'pending'): ?>
                        <?php if (!empty($item['payment_note'])): ?>
                            <div class="alert alert-light border small">
                                <div class="fw-semibold mb-1">Platební pokyny</div>
                                <?= nl2br(e($item['payment_note'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($item['payment_claimed_at'])): ?>
                            <p class="text-muted small">
                                Platba byla oznámena <?= e(format_datetime($item['payment_claimed_at'])) ?>.
                                Čeká na potvrzení správcem události.
                            </p>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/registrace/' . $item['id'] . '/uhrazeno')) ?>" class="mb-2">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success">Uhradil jsem</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                        <form method="post" action="<?= e(url('/registrace/' . $item['id'] . '/zrusit')) ?>"
                              data-confirm="Opravdu zrušit registraci na událost <?= e($item['title']) ?>?">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Zrušit registraci</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
