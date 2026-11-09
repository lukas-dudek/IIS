<?php

declare(strict_types=1);

$current = rtrim($currentPath, '/');
$isVenues = str_starts_with($current . '/', rtrim(url('/moderace/mista'), '/') . '/');
$isCategories = str_starts_with($current . '/', rtrim(url('/moderace/kategorie'), '/') . '/');
$isComments = str_starts_with($current . '/', rtrim(url('/moderace/komentare'), '/') . '/');
?>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link<?= $isVenues ? ' active' : '' ?>" href="<?= e(url('/moderace/mista')) ?>"
           <?= $isVenues ? 'aria-current="page"' : '' ?>>Místa</a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $isCategories ? ' active' : '' ?>" href="<?= e(url('/moderace/kategorie')) ?>"
           <?= $isCategories ? 'aria-current="page"' : '' ?>>Kategorie</a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $isComments ? ' active' : '' ?>" href="<?= e(url('/moderace/komentare')) ?>"
           <?= $isComments ? 'aria-current="page"' : '' ?>>Komentáře</a>
    </li>
</ul>
