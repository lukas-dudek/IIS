<?php

declare(strict_types=1);

$capacity = $event['capacity'] === null ? null : (int) $event['capacity'];
$ratingCount = (int) $rating['count'];
$returnPath = '/udalosti/' . $event['slug'];
?>
<div class="mb-3">
    <a href="<?= e(url('/udalosti')) ?>">← Zpět na události</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <h1 class="h3 mb-3"><?= e($event['title']) ?></h1>

            <p class="mb-3">
                <strong><?= e(format_date_range($event['starts_at'], $event['ends_at'])) ?></strong><br>
                <a href="<?= e(url('/mista/' . $event['venue_id'])) ?>"><?= e($event['venue_name']) ?></a>,
                <?= e($event['venue_city']) ?><br>
                <?= e($event['category_name']) ?>
            </p>

            <p class="mb-4"><?= nl2br(e($event['description'])) ?></p>

            <dl class="row small mb-0">
                <dt class="col-sm-4">Kapacita</dt>
                <dd class="col-sm-8"><?= e(event_occupancy((int) $event['active_registrations'], $capacity)) ?></dd>
                <dt class="col-sm-4">Hodnocení</dt>
                <dd class="col-sm-8">
                    <?php if ($ratingCount > 0): ?>
                        <?= e(number_format((float) $rating['average'], 1, ',', '.')) ?> / 5
                        (<?= $ratingCount ?> hodnocení)
                    <?php else: ?>
                        Zatím bez hodnocení
                    <?php endif; ?>
                </dd>
            </dl>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h5 mb-3">Vstupenky</h2>
            <?php if ($tickets === []): ?>
                <p class="text-muted mb-0">Událost nemá zveřejněné typy vstupenek.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Typ</th>
                            <th scope="col">Cena</th>
                            <th scope="col">Dostupnost</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            $ticketCapacity = $ticket['capacity'] === null ? null : (int) $ticket['capacity'];
                            $active = (int) $ticket['active_count'];
                            $remaining = $ticketCapacity === null ? null : max(0, $ticketCapacity - $active);
                            ?>
                            <tr>
                                <td><?= e($ticket['name']) ?></td>
                                <td><?= e(format_money($ticket['price'])) ?></td>
                                <td class="small text-muted">
                                    <?php if ($ticketCapacity === null): ?>
                                        Bez limitu
                                    <?php elseif ($remaining === 0): ?>
                                        Obsazeno
                                    <?php else: ?>
                                        Zbývá <?= $remaining ?> z <?= $ticketCapacity ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($user === null): ?>
                <p class="mt-3 mb-0">
                    <a class="btn btn-primary"
                       href="<?= e(url('/prihlaseni') . '?return=' . rawurlencode($returnPath)) ?>">
                        Přihlásit se pro registraci
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4">
            <h2 class="h5 mb-3">Komentáře</h2>
            <?php if (!$commentsOpen): ?>
                <p class="text-muted mb-0">Komentáře a hodnocení se zobrazí po ukončení události.</p>
            <?php elseif ($comments === []): ?>
                <p class="text-muted mb-0">Zatím žádné komentáře.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($comments as $comment): ?>
                        <li class="border-bottom py-3">
                            <div class="fw-semibold"><?= e($comment['author_name']) ?></div>
                            <div class="small text-muted mb-1"><?= e(format_datetime($comment['created_at'])) ?></div>
                            <?= nl2br(e($comment['body'])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4">
            <h2 class="h6 mb-2">Místo konání</h2>
            <p class="mb-1 fw-semibold"><?= e($event['venue_name']) ?></p>
            <p class="text-muted small mb-3">
                <?= e($event['venue_street']) ?><br>
                <?= e(trim(($event['venue_postal_code'] ?? '') . ' ' . $event['venue_city'])) ?>
            </p>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/mista/' . $event['venue_id'])) ?>">
                Detail místa
            </a>
        </div>
    </div>
</div>
