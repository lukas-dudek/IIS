<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="h4 mb-1">Účastníci události</h1>
        <p class="text-muted small mb-0">
            <?= e($event['title']) ?> · <?= e(format_date_range($event['starts_at'], $event['ends_at'])) ?>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/moje-udalosti')) ?>">Zpět na moje události</a>
</div>

<form method="get" action="<?= e(url('/udalosti/' . $event['id'] . '/ucastnici')) ?>" class="bg-white border rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label for="stav" class="form-label">Stav registrace</label>
            <select class="form-select" id="stav" name="stav">
                <option value="">Všechny</option>
                <option value="pending"<?= selected($status, 'pending') ?>>Čeká na platbu</option>
                <option value="confirmed"<?= selected($status, 'confirmed') ?>>Potvrzeno</option>
                <option value="cancelled"<?= selected($status, 'cancelled') ?>>Zrušeno</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Filtrovat</button>
        </div>
    </div>
</form>

<?php if ($registrations === []): ?>
    <div class="alert alert-info">Žádné registrace neodpovídají filtru.</div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Účastník</th>
                <th scope="col">Vstupenka</th>
                <th scope="col">Cena</th>
                <th scope="col">Stav</th>
                <th scope="col">VS</th>
                <th scope="col">Platba oznámena</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($registrations as $item): ?>
                <?php
                $badge = match ($item['status']) {
                    'confirmed' => 'success',
                    'pending' => 'warning',
                    default => 'secondary',
                };
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($item['user_name']) ?></div>
                        <div class="small text-muted"><?= e($item['user_email']) ?></div>
                    </td>
                    <td><?= e($item['ticket_name']) ?></td>
                    <td><?= e(format_money($item['price'])) ?></td>
                    <td><span class="badge text-bg-<?= $badge ?>"><?= e(registration_status_label($item['status'])) ?></span></td>
                    <td><code><?= e($item['variable_symbol'] ?? '—') ?></code></td>
                    <td class="small"><?= e(format_datetime($item['payment_claimed_at']) ?: '—') ?></td>
                    <td class="text-end">
                        <?php if ($item['status'] === 'pending'): ?>
                            <form method="post" class="d-inline"
                                  action="<?= e(url('/registrace/' . $item['id'] . '/potvrdit')) ?>"
                                  data-confirm="Potvrdit platbu účastníka <?= e($item['user_name']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success">Potvrdit</button>
                            </form>
                            <form method="post" class="d-inline"
                                  action="<?= e(url('/registrace/' . $item['id'] . '/odmitnout')) ?>"
                                  data-confirm="Odmítnout platbu a zrušit registraci účastníka <?= e($item['user_name']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Odmítnout</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($item['status'], ['pending', 'confirmed'], true) && !is_past($event['starts_at'])): ?>
                            <form method="post" class="d-inline"
                                  action="<?= e(url('/registrace/' . $item['id'] . '/zrusit')) ?>"
                                  data-confirm="Zrušit registraci účastníka <?= e($item['user_name']) ?>?">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Zrušit</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
