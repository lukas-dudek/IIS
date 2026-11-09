<?php

declare(strict_types=1);

$address = trim(($venue['postal_code'] ?? '') . ' ' . $venue['city']);
?>
<div class="mb-3">
    <a href="<?= e(url('/mista')) ?>">← Zpět na místa</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <h1 class="h3 mb-3"><?= e($venue['name']) ?></h1>
            <p class="mb-3">
                <?= e($venue['street']) ?><br>
                <?= e($address) ?>
            </p>
            <?php if (!empty($venue['description'])): ?>
                <p class="mb-0"><?= nl2br(e($venue['description'])) ?></p>
            <?php endif; ?>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h5 mb-3">Budoucí akce</h2>
            <?php if ($events === []): ?>
                <p class="text-muted mb-0">V tomto místě zatím nejsou žádné budoucí akce.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($events as $event): ?>
                        <li class="border-bottom py-2">
                            <div class="fw-semibold">
                                <a class="text-decoration-none" href="<?= e(url('/udalosti/' . $event['slug'])) ?>">
                                    <?= e($event['title']) ?>
                                </a>
                            </div>
                            <div class="small text-muted">
                                <?= e(format_date_range($event['starts_at'], $event['ends_at'])) ?>
                                · <?= e($event['category_name']) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($venue['photo_path'])): ?>
        <div class="col-lg-4">
            <img src="<?= e(url('/uploads/' . $venue['photo_path'])) ?>"
                 alt="<?= e($venue['name']) ?>" class="img-fluid rounded-3 border">
        </div>
    <?php endif; ?>
</div>
