<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
    <h1 class="h4 mb-0">Moderace událostí</h1>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($events === []): ?>
    <div class="alert alert-info">Žádné události nečekají na schválení.</div>
<?php else: ?>
    <div class="table-responsive bg-white border rounded-3">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Událost</th>
                <th scope="col">Správce</th>
                <th scope="col">Místo</th>
                <th scope="col">Kategorie</th>
                <th scope="col">Termín</th>
                <th scope="col">Kapacita</th>
                <th scope="col">Vstupenky</th>
                <th scope="col">Odesláno</th>
                <th scope="col" class="text-end">Akce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-decoration-none"
                           href="<?= e(url('/moderace/udalosti/' . $event['id'])) ?>">
                            <?= e($event['title']) ?>
                        </a>
                        <?php if (!empty($event['collisions'])): ?>
                            <div class="small text-danger">
                                Kolize s <?= count($event['collisions']) ?>
                                <?= count($event['collisions']) === 1 ? 'událostí' : 'událostmi' ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($event['manager_name']) ?></td>
                    <td class="small">
                        <?= e($event['venue_name']) ?><br>
                        <span class="text-muted"><?= e($event['venue_city']) ?></span>
                    </td>
                    <td class="small"><?= e($event['category_name']) ?></td>
                    <td class="small"><?= e(format_date_range($event['starts_at'], $event['ends_at'])) ?></td>
                    <td class="small">
                        <?= $event['capacity'] === null ? 'Neomezeno' : (int) $event['capacity'] ?>
                        <br><span class="text-muted"><?= (int) $event['active_registrations'] ?> aktivních</span>
                    </td>
                    <td class="small">
                        <?php foreach ($event['tickets'] as $ticket): ?>
                            <div><?= e($ticket['name']) ?> · <?= e(format_money($ticket['price'])) ?></div>
                        <?php endforeach; ?>
                    </td>
                    <td class="small"><?= e(format_datetime($event['submitted_at']) ?: '—') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('/moderace/udalosti/' . $event['id'])) ?>">Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
