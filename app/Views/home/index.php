<?php

declare(strict_types=1);
?>
<div class="p-4 p-md-5 bg-white border rounded-3 mb-4">
    <h1 class="h3 mb-2">Co se bude dít?</h1>
    <p class="text-muted mb-3">
        Procházejte schválené události, filtrujte podle kategorie, města a termínu.
    </p>
    <form method="get" action="<?= e(url('/udalosti')) ?>" class="row g-2">
        <div class="col-md-8">
            <label for="q" class="visually-hidden">Hledat události</label>
            <input type="text" class="form-control form-control-lg" id="q" name="q"
                   placeholder="Název, popis, místo nebo město">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary btn-lg w-100">Hledat události</button>
        </div>
    </form>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h2 class="h4 mb-0">Nejbližší události</h2>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/udalosti')) ?>">Všechny události</a>
</div>

<?php if ($events === []): ?>
    <div class="alert alert-info">
        Zatím tu nejsou žádné budoucí schválené události.
        <a href="<?= e(url('/udalosti')) ?>">Zobrazit katalog</a>.
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($events as $item): ?>
            <div class="col-md-6 col-xl-4">
                <?php require __DIR__ . '/../partials/event-card.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="h4 mb-3">Kategorie</h2>
<?php if ($categories === []): ?>
    <p class="text-muted">Zatím nejsou schválené žádné kategorie.</p>
<?php else: ?>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($categories as $category): ?>
            <a class="btn btn-sm btn-outline-secondary"
               href="<?= e(url('/udalosti') . '?kategorie=' . (int) $category['id']) ?>">
                <?= e($category['name']) ?>
            </a>
            <?php foreach ($category['children'] as $child): ?>
                <a class="btn btn-sm btn-link text-decoration-none"
                   href="<?= e(url('/udalosti') . '?kategorie=' . (int) $child['id']) ?>">
                    <?= e($child['name']) ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
