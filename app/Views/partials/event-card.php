<?php

declare(strict_types=1);

$capacity = $item['capacity'] === null ? null : (int) $item['capacity'];
$active = (int) $item['active_registrations'];
$full = $capacity !== null && $active >= $capacity;
$ratingCount = (int) $item['rating_count'];
?>
<div class="card h-100 border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <h2 class="h5 mb-0">
                <a class="text-decoration-none" href="<?= e(url('/udalosti/' . $item['slug'])) ?>">
                    <?= e($item['title']) ?>
                </a>
            </h2>
            <?php if ($full): ?>
                <span class="badge text-bg-warning">Obsazeno</span>
            <?php endif; ?>
        </div>

        <p class="text-muted small mb-2">
            <?= e(format_date_range($item['starts_at'], $item['ends_at'])) ?><br>
            <?= e($item['venue_name']) ?>, <?= e($item['venue_city']) ?> · <?= e($item['category_name']) ?>
        </p>

        <div class="d-flex flex-wrap gap-3 small mb-3">
            <span class="fw-semibold"><?= e(event_price_range($item['min_price'], $item['max_price'])) ?></span>
            <span class="text-muted">Obsazenost: <?= e(event_occupancy($active, $capacity)) ?></span>
            <span class="text-muted">
                <?php if ($ratingCount > 0): ?>
                    <?= e(number_format((float) $item['rating_avg'], 1, ',', '.')) ?> / 5 (<?= $ratingCount ?>)
                <?php else: ?>
                    Bez hodnocení
                <?php endif; ?>
            </span>
        </div>

        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/udalosti/' . $item['slug'])) ?>">
            Zobrazit detail
        </a>
    </div>
</div>
