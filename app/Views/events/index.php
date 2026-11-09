<?php

declare(strict_types=1);

$query = [
    'q' => $q,
    'kategorie' => $categoryId > 0 ? (string) $categoryId : '',
    'mesto' => $city,
    'od' => $from,
    'do' => $to,
    'vstupne' => $price,
    'dostupnost' => $availability,
    'archiv' => $archive ? '1' : '',
    'razeni' => $sort,
];
$filterUrl = static function (array $overrides) use ($query): string {
    $params = array_filter(array_merge($query, $overrides), static fn ($value): bool => $value !== '' && $value !== null);
    $queryString = http_build_query($params);

    return url('/udalosti') . ($queryString === '' ? '' : '?' . $queryString);
};
$categoryNames = [];
foreach ($categories as $category) {
    $categoryNames[(int) $category['id']] = $category['name'];
}
$hasFilters = $q !== '' || $categoryId > 0 || $city !== '' || $from !== '' || $to !== ''
    || $price !== '' || $availability !== '' || $archive;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0">Události</h1>
    <span class="text-muted"><?= (int) $total ?> výsledků</span>
</div>

<form method="get" action="<?= e(url('/udalosti')) ?>" class="bg-white border rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label for="q" class="form-label">Hledat</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Název, popis, místo nebo město">
        </div>
        <div class="col-md-4 col-lg-2">
            <label for="kategorie" class="form-label">Kategorie</label>
            <select class="form-select" id="kategorie" name="kategorie">
                <option value="">Všechny</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"<?= selected($categoryId, $category['id']) ?>>
                        <?= str_repeat('— ', max(0, (int) $category['depth'] - 1)) . e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label for="mesto" class="form-label">Město</label>
            <select class="form-select" id="mesto" name="mesto">
                <option value="">Všechna</option>
                <?php foreach ($cities as $cityOption): ?>
                    <option value="<?= e($cityOption) ?>"<?= selected($city, $cityOption) ?>>
                        <?= e($cityOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-2">
            <label for="od" class="form-label">Od</label>
            <input type="date" class="form-control" id="od" name="od" value="<?= e($from) ?>">
        </div>
        <div class="col-md-3 col-lg-2">
            <label for="do" class="form-label">Do</label>
            <input type="date" class="form-control" id="do" name="do" value="<?= e($to) ?>">
        </div>

        <div class="col-md-4 col-lg-2">
            <label for="vstupne" class="form-label">Vstupné</label>
            <select class="form-select" id="vstupne" name="vstupne">
                <option value="">Vše</option>
                <option value="free"<?= selected($price, 'free') ?>>Zdarma</option>
                <option value="paid"<?= selected($price, 'paid') ?>>Placené</option>
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label for="dostupnost" class="form-label">Dostupnost</label>
            <select class="form-select" id="dostupnost" name="dostupnost">
                <option value="">Vše</option>
                <option value="available"<?= selected($availability, 'available') ?>>S volnými místy</option>
                <option value="full"<?= selected($availability, 'full') ?>>Obsazené</option>
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label for="razeni" class="form-label">Řazení</label>
            <select class="form-select" id="razeni" name="razeni">
                <option value="date"<?= selected($sort, 'date') ?>>Podle data</option>
                <option value="price"<?= selected($sort, 'price') ?>>Podle ceny</option>
                <option value="title"<?= selected($sort, 'title') ?>>Podle názvu</option>
            </select>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="archiv" name="archiv" value="1"<?= checked($archive) ?>>
                <label class="form-check-label" for="archiv">Zobrazit i proběhlé</label>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Filtrovat</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/udalosti')) ?>">Zrušit</a>
        </div>
    </div>
</form>

<?php if ($hasFilters): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($q !== ''): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['q' => ''])) ?>">
                Hledání: <?= e($q) ?> ×
            </a>
        <?php endif; ?>
        <?php if ($categoryId > 0): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['kategorie' => ''])) ?>">
                Kategorie: <?= e($categoryNames[$categoryId] ?? ('#' . $categoryId)) ?> ×
            </a>
        <?php endif; ?>
        <?php if ($city !== ''): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['mesto' => ''])) ?>">
                Město: <?= e($city) ?> ×
            </a>
        <?php endif; ?>
        <?php if ($from !== ''): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['od' => ''])) ?>">
                Od: <?= e($from) ?> ×
            </a>
        <?php endif; ?>
        <?php if ($to !== ''): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['do' => ''])) ?>">
                Do: <?= e($to) ?> ×
            </a>
        <?php endif; ?>
        <?php if ($price !== ''): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['vstupne' => ''])) ?>">
                Vstupné: <?= $price === 'free' ? 'zdarma' : 'placené' ?> ×
            </a>
        <?php endif; ?>
        <?php if ($availability !== ''): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['dostupnost' => ''])) ?>">
                Dostupnost: <?= $availability === 'available' ? 'volná místa' : 'obsazené' ?> ×
            </a>
        <?php endif; ?>
        <?php if ($archive): ?>
            <a class="badge text-bg-light text-decoration-none border" href="<?= e($filterUrl(['archiv' => ''])) ?>">
                Včetně proběhlých ×
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($events === []): ?>
    <div class="alert alert-info">
        Žádné události neodpovídají filtru.
        <a href="<?= e(url('/udalosti')) ?>">Zrušte filtry</a> nebo se podívejte na všechny události.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($events as $item): ?>
            <div class="col-md-6 col-xl-4">
                <?php require __DIR__ . '/../partials/event-card.php'; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4" aria-label="Stránkování událostí">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($filterUrl(['strana' => (string) max(1, $page - 1)])) ?>">Předchozí</a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item<?= $p === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= e($filterUrl(['strana' => (string) $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                    <a class="page-link" href="<?= e($filterUrl(['strana' => (string) min($totalPages, $page + 1)])) ?>">Další</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
