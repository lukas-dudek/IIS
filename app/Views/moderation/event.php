<?php

declare(strict_types=1);

$badge = match ($event['status']) {
    'approved' => 'success',
    'pending' => 'warning',
    'rejected' => 'danger',
    'cancelled' => 'secondary',
    default => 'secondary',
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <a href="<?= e(url('/moderace/udalosti')) ?>">← Zpět na frontu událostí</a>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <h1 class="h3 mb-0"><?= e($event['title']) ?></h1>
                <span class="badge text-bg-<?= $badge ?>"><?= e(event_status_label($event['status'])) ?></span>
            </div>

            <dl class="row small mb-3">
                <dt class="col-sm-4">Správce</dt>
                <dd class="col-sm-8"><?= e($event['manager_name']) ?></dd>
                <dt class="col-sm-4">Místo</dt>
                <dd class="col-sm-8"><?= e($event['venue_name']) ?>, <?= e($event['venue_city']) ?></dd>
                <dt class="col-sm-4">Kategorie</dt>
                <dd class="col-sm-8"><?= e($event['category_name']) ?></dd>
                <dt class="col-sm-4">Termín</dt>
                <dd class="col-sm-8"><?= e(format_date_range($event['starts_at'], $event['ends_at'])) ?></dd>
                <dt class="col-sm-4">Kapacita</dt>
                <dd class="col-sm-8">
                    <?= $event['capacity'] === null ? 'Neomezeno' : (int) $event['capacity'] ?>
                    · <?= (int) $event['active_registrations'] ?> aktivních registrací
                </dd>
                <dt class="col-sm-4">Odesláno ke schválení</dt>
                <dd class="col-sm-8"><?= e(format_datetime($event['submitted_at']) ?: '—') ?></dd>
            </dl>

            <p class="mb-3"><?= nl2br(e($event['description'])) ?></p>

            <?php if (!empty($event['payment_note'])): ?>
                <div class="alert alert-light border small">
                    <div class="fw-semibold mb-1">Platební pokyny</div>
                    <?= nl2br(e($event['payment_note'])) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['rejection_reason'])): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Důvod zamítnutí</div>
                    <?= e($event['rejection_reason']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h5 mb-3">Typy vstupenek</h2>
            <?php if ($tickets === []): ?>
                <p class="text-muted mb-0">Událost nemá žádné typy vstupenek.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Název</th>
                            <th scope="col">Cena</th>
                            <th scope="col">Kapacita</th>
                            <th scope="col">Aktivní</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?= e($ticket['name']) ?></td>
                                <td><?= e(format_money($ticket['price'])) ?></td>
                                <td><?= $ticket['capacity'] === null ? 'Bez limitu' : (int) $ticket['capacity'] ?></td>
                                <td><?= (int) $ticket['active_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($event['status'] === 'pending'): ?>
            <div class="bg-white border rounded-3 p-4 mt-4">
                <h2 class="h5 mb-3">Schválení nebo zamítnutí</h2>

                <form method="post" action="<?= e(url('/moderace/udalosti/' . $event['id'] . '/schvalit')) ?>" class="mb-3"
                      data-confirm="Opravdu schválit událost <?= e($event['title']) ?>?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success">Schválit událost</button>
                </form>

                <form method="post" action="<?= e(url('/moderace/udalosti/' . $event['id'] . '/zamitnout')) ?>" novalidate>
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
                    <button type="submit" class="btn btn-outline-danger">Zamítnout událost</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if (!empty($collisions)): ?>
            <div class="alert alert-warning">
                <div class="fw-semibold mb-2">Kolize v místě a čase</div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($collisions as $collision): ?>
                        <li class="mb-1">
                            <a href="<?= e(url('/moderace/udalosti/' . $collision['id'])) ?>">
                                <?= e($collision['title']) ?>
                            </a><br>
                            <span class="small">
                                <?= e(format_date_range($collision['starts_at'], $collision['ends_at'])) ?>
                                · <?= e(event_status_label($collision['status'])) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white border rounded-3 p-4">
            <h2 class="h6 mb-2">Místo konání</h2>
            <p class="mb-1 fw-semibold"><?= e($event['venue_name']) ?></p>
            <p class="text-muted small mb-0"><?= e($event['venue_city']) ?></p>
        </div>
    </div>
</div>
