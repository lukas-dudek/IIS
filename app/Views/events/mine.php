<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Moje události</h1>
    <a class="btn btn-primary" href="<?= e(url('/udalosti/nova')) ?>">Nová událost</a>
</div>

<?php if ($events === []): ?>
    <div class="alert alert-info">
        Zatím nemáte žádné události.
        <a href="<?= e(url('/udalosti/nova')) ?>">Vytvořte první událost</a>.
    </div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Událost</th>
                <th scope="col">Termín</th>
                <th scope="col">Místo</th>
                <th scope="col">Kategorie</th>
                <th scope="col">Kapacita</th>
                <th scope="col">Registrace</th>
                <th scope="col">Stav</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $item): ?>
                <?php
                $badge = match ($item['status']) {
                    'approved' => 'success',
                    'pending' => 'warning',
                    'rejected' => 'danger',
                    'cancelled' => 'secondary',
                    default => 'secondary',
                };
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($item['title']) ?></div>
                        <?php if (!empty($item['rejection_reason'])): ?>
                            <div class="small text-danger"><?= e($item['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e(format_date_range($item['starts_at'], $item['ends_at'])) ?></td>
                    <td class="small">
                        <?= e($item['venue_name']) ?><br>
                        <span class="text-muted"><?= e($item['venue_city']) ?></span>
                    </td>
                    <td class="small"><?= e($item['category_name']) ?></td>
                    <td class="small">
                        <?= $item['capacity'] === null ? 'Neomezeno' : (int) $item['capacity'] ?>
                    </td>
                    <td class="small"><?= (int) $item['active_registrations'] ?></td>
                    <td>
                        <span class="badge text-bg-<?= $badge ?>"><?= e(event_status_label($item['status'])) ?></span>
                    </td>
                    <td class="text-end">
                        <?php if ($item['status'] !== 'cancelled'): ?>
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('/udalosti/' . $item['id'] . '/upravit')) ?>">Upravit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
