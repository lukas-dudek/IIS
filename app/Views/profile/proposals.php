<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Moje návrhy</h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="<?= e(url('/mista/navrhnout')) ?>">Navrhnout místo</a>
        <a class="btn btn-outline-primary" href="<?= e(url('/kategorie/navrhnout')) ?>">Navrhnout kategorii</a>
    </div>
</div>

<h2 class="h5 mb-3">Místa</h2>

<?php if ($venues === []): ?>
    <div class="alert alert-info">
        Zatím jste nenavrhli žádné místo.
        <a href="<?= e(url('/mista/navrhnout')) ?>">Navrhněte první místo</a>.
    </div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Místo</th>
                <th scope="col">Adresa</th>
                <th scope="col">Stav</th>
                <th scope="col">Vytvořeno</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($venues as $item): ?>
                <?php
                $badge = match ($item['status']) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'warning',
                };
                $editable = in_array($item['status'], ['pending', 'rejected'], true);
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($item['name']) ?></div>
                        <?php if (!empty($item['rejection_reason'])): ?>
                            <div class="small text-danger"><?= e($item['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= e($item['street']) ?><br>
                        <?= e(trim(($item['postal_code'] ?? '') . ' ' . $item['city'])) ?>
                    </td>
                    <td>
                        <span class="badge text-bg-<?= $badge ?>"><?= e(moderation_status_label($item['status'])) ?></span>
                    </td>
                    <td class="small"><?= e(format_datetime($item['created_at']) ?: '—') ?></td>
                    <td class="text-end">
                        <?php if ($editable): ?>
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('/mista/' . $item['id'] . '/upravit')) ?>">Upravit návrh</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/mista/' . $item['id'])) ?>">
                                Zobrazit
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2 class="h5 mb-3 mt-4">Kategorie</h2>

<?php if ($categories === []): ?>
    <div class="alert alert-info">
        Zatím jste nenavrhli žádnou kategorii.
        <a href="<?= e(url('/kategorie/navrhnout')) ?>">Navrhněte první kategorii</a>.
    </div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Kategorie</th>
                <th scope="col">Rodič</th>
                <th scope="col">Stav</th>
                <th scope="col">Vytvořeno</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $item): ?>
                <?php
                $badge = match ($item['status']) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'warning',
                };
                $editable = in_array($item['status'], ['pending', 'rejected'], true);
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($item['name']) ?></div>
                        <?php if (!empty($item['rejection_reason'])): ?>
                            <div class="small text-danger"><?= e($item['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($item['parent_name'] ?? '—') ?></td>
                    <td>
                        <span class="badge text-bg-<?= $badge ?>"><?= e(moderation_status_label($item['status'])) ?></span>
                    </td>
                    <td class="small"><?= e(format_datetime($item['created_at']) ?: '—') ?></td>
                    <td class="text-end">
                        <?php if ($editable): ?>
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('/kategorie/' . $item['id'] . '/upravit')) ?>">Upravit návrh</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
