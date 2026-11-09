<?php

declare(strict_types=1);

$cells = $startWeekday - 1;
$isCurrentMonth = $month->format('Y-m') === $currentMonth;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Můj kalendář</h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/muj-kalendar') . '?mesic=' . $previousMonth) ?>">
            ← Předchozí
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/muj-kalendar') . '?mesic=' . $currentMonth) ?>">
            Dnes
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/muj-kalendar') . '?mesic=' . $nextMonth) ?>">
            Další →
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/muj-kalendar.ics')) ?>">Exportovat .ics</a>
    </div>
</div>

<h2 class="h5 mb-3"><?= e($monthLabel) ?></h2>

<div class="calendar-grid d-none d-md-block">
    <div class="table-responsive bg-white border rounded-3 p-2">
        <table class="table table-bordered mb-0 calendar-table">
            <thead>
            <tr>
                <th scope="col">Po</th>
                <th scope="col">Út</th>
                <th scope="col">St</th>
                <th scope="col">Čt</th>
                <th scope="col">Pá</th>
                <th scope="col">So</th>
                <th scope="col">Ne</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <?php for ($blank = 0; $blank < $cells; $blank++): ?>
                    <td class="bg-light"></td>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php $isToday = $isCurrentMonth && $day === $todayDay; ?>
                    <td class="<?= $isToday ? 'table-primary' : '' ?>">
                        <div class="fw-semibold small mb-1"><?= $day ?></div>
                        <?php foreach ($eventsByDay[$day] ?? [] as $event): ?>
                            <div class="calendar-event">
                                <a class="text-decoration-none"
                                   href="<?= e(url('/udalosti/' . $event['slug'])) ?>">
                                    <?= e(format_datetime($event['starts_at'], 'H:i')) ?>
                                    <?= e($event['title']) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <?php $cells++; ?>
                    <?php if ($cells % 7 === 0 && $day < $daysInMonth): ?>
                        </tr><tr>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php while ($cells % 7 !== 0): ?>
                    <td class="bg-light"></td>
                    <?php $cells++; ?>
                <?php endwhile; ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<h2 class="h5 mt-4 mb-3">Události v měsíci</h2>
<?php if ($events === []): ?>
    <div class="alert alert-info">
        V tomto měsíci nemáte žádné události.
        <a href="<?= e(url('/udalosti')) ?>">Procházet události</a>.
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($events as $event): ?>
            <?php $isManager = (int) $event['manager_id'] === (int) $user['id']; ?>
            <div class="list-group-item">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <a class="fw-semibold text-decoration-none" href="<?= e(url('/udalosti/' . $event['slug'])) ?>">
                            <?= e($event['title']) ?>
                        </a>
                        <div class="small text-muted">
                            <?= e(format_date_range($event['starts_at'], $event['ends_at'])) ?>
                            · <?= e($event['venue_name']) ?>, <?= e($event['venue_city']) ?>
                            · <?= e($event['category_name']) ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($isManager): ?>
                            <span class="badge text-bg-primary">Spravuji</span>
                        <?php else: ?>
                            <span class="badge text-bg-success">Účastním se</span>
                        <?php endif; ?>
                        <?php if ($isManager): ?>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= e(url('/udalosti/' . $event['id'] . '/ucastnici')) ?>">Účastníci</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
