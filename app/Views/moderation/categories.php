<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
    <h1 class="h4 mb-0">Moderace kategorií</h1>
    <a class="btn btn-primary" href="<?= e(url('/moderace/kategorie/nova')) ?>">Nová kategorie</a>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($categories === []): ?>
    <div class="alert alert-info">Zatím tu nejsou žádné kategorie.</div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Kategorie</th>
                <th scope="col">Rodič</th>
                <th scope="col">Stav</th>
                <th scope="col">Navrhl</th>
                <th scope="col">Vytvořeno</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $item): ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-decoration-none"
                           href="<?= e(url('/moderace/kategorie/' . $item['id'])) ?>">
                            <?= str_repeat('— ', max(0, (int) $item['depth'] - 1)) . e($item['name']) ?>
                        </a>
                    </td>
                    <td class="small text-muted"><?= e($item['parent_name'] ?? '—') ?></td>
                    <td>
                        <?php
                        $badge = match ($item['status']) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        };
                        ?>
                        <span class="badge text-bg-<?= $badge ?>"><?= e(moderation_status_label($item['status'])) ?></span>
                    </td>
                    <td class="small"><?= e($item['proposer_name']) ?></td>
                    <td class="small"><?= e(format_datetime($item['created_at']) ?: '—') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('/moderace/kategorie/' . $item['id'])) ?>">Detail</a>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= e(url('/moderace/kategorie/' . $item['id'] . '/upravit')) ?>">Upravit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
