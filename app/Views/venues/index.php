<?php

declare(strict_types=1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Místa konání</h1>
    <?php if ($user !== null): ?>
        <a class="btn btn-primary" href="<?= e(url('/mista/navrhnout')) ?>">Navrhnout místo</a>
    <?php endif; ?>
</div>

<?php if ($venues === []): ?>
    <div class="alert alert-info">
        Zatím tu nejsou žádná schválená místa.
        <?php if ($user !== null): ?>
            <a href="<?= e(url('/mista/navrhnout')) ?>">Navrhněte první místo</a>.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($venues as $venue): ?>
            <div class="col-md-6">
                <div class="bg-white border rounded-3 p-3 h-100 d-flex gap-3">
                    <?php if (!empty($venue['photo_path'])): ?>
                        <img src="<?= e(url('/uploads/' . $venue['photo_path'])) ?>"
                             alt="<?= e($venue['name']) ?>" class="rounded flex-shrink-0 venue-thumb">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <h2 class="h5 mb-1">
                            <a class="text-decoration-none" href="<?= e(url('/mista/' . $venue['id'])) ?>">
                                <?= e($venue['name']) ?>
                            </a>
                        </h2>
                        <p class="mb-2 text-muted small">
                            <?= e($venue['street']) ?><br>
                            <?= e(trim(($venue['postal_code'] ?? '') . ' ' . $venue['city'])) ?>
                        </p>
                        <p class="mb-2">
                            <?php $count = (int) $venue['upcoming_count']; ?>
                            <?php if ($count === 0): ?>
                                Žádné budoucí akce
                            <?php elseif ($count === 1): ?>
                                1 budoucí akce
                            <?php else: ?>
                                <?= $count ?> budoucích akcí
                            <?php endif; ?>
                        </p>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/mista/' . $venue['id'])) ?>">
                            Zobrazit detail
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
