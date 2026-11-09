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

            <?php
            $registrationActive = $registration !== null
                && in_array($registration['status'], ['pending', 'confirmed'], true);
            $eventFull = $event['capacity'] !== null
                && (int) $event['active_registrations'] >= (int) $event['capacity'];
            $eventStarted = is_past($event['starts_at']);
            $availableTickets = array_filter(
                $tickets,
                static fn (array $ticket): bool => $ticket['capacity'] === null
                    || (int) $ticket['active_count'] < (int) $ticket['capacity']
            );
            ?>
            <?php if ($user === null): ?>
                <p class="mt-3 mb-0">
                    <a class="btn btn-primary"
                       href="<?= e(url('/prihlaseni') . '?return=' . rawurlencode($returnPath)) ?>">
                        Přihlásit se pro registraci
                    </a>
                </p>
            <?php elseif ($registrationActive): ?>
                <div class="mt-3">
                    <?php $regBadge = $registration['status'] === 'confirmed' ? 'success' : 'warning'; ?>
                    <p class="mb-1">
                        Vaše registrace:
                        <span class="badge text-bg-<?= $regBadge ?>">
                            <?= e(registration_status_label($registration['status'])) ?>
                        </span>
                    </p>
                    <?php if ($registration['status'] === 'pending'): ?>
                        <p class="small mb-1">
                            Variabilní symbol: <code><?= e($registration['variable_symbol'] ?? '—') ?></code>
                        </p>
                        <?php if (!empty($event['payment_note'])): ?>
                            <p class="small text-muted mb-1"><?= nl2br(e($event['payment_note'])) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/moje-registrace')) ?>">
                        Zobrazit moje registrace
                    </a>
                </div>
            <?php elseif ($eventStarted): ?>
                <p class="mt-3 mb-0 text-muted">Registrace už není možná, událost již začala.</p>
            <?php elseif ($eventFull): ?>
                <p class="mt-3 mb-0 text-muted">Kapacita události je plná.</p>
            <?php elseif ($availableTickets === []): ?>
                <p class="mt-3 mb-0 text-muted">Všechny typy vstupenek jsou obsazené.</p>
            <?php else: ?>
                <?php if ($registration !== null && $registration['status'] === 'cancelled'): ?>
                    <div class="alert alert-info mt-3 py-2 small">
                        Vaše předchozí registrace byla zrušena. Můžete se znovu registrovat.
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/udalosti/' . $event['slug'] . '/registrace')) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <fieldset>
                        <legend class="h6 mb-2">Vyberte typ vstupenky</legend>
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            $ticketCapacity = $ticket['capacity'] === null ? null : (int) $ticket['capacity'];
                            $active = (int) $ticket['active_count'];
                            $ticketFull = $ticketCapacity !== null && $active >= $ticketCapacity;
                            ?>
                            <div class="form-check border rounded p-3 mb-2<?= $ticketFull ? ' bg-light' : '' ?>">
                                <input class="form-check-input" type="radio" name="ticket_type_id"
                                       id="ticket-type-<?= (int) $ticket['id'] ?>" value="<?= (int) $ticket['id'] ?>"
                                       required<?= $ticketFull ? ' disabled' : '' ?>>
                                <label class="form-check-label d-flex justify-content-between gap-3"
                                       for="ticket-type-<?= (int) $ticket['id'] ?>">
                                    <span>
                                        <span class="fw-semibold"><?= e($ticket['name']) ?></span><br>
                                        <span class="small text-muted">
                                            <?php if ($ticketFull): ?>
                                                Obsazeno
                                            <?php elseif ($ticketCapacity === null): ?>
                                                Bez limitu
                                            <?php else: ?>
                                                Zbývá <?= max(0, $ticketCapacity - $active) ?> z <?= $ticketCapacity ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                    <span class="fw-semibold"><?= e(format_money($ticket['price'])) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                    <button type="submit" class="btn btn-primary">Registrovat se</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4" id="hodnoceni">
            <h2 class="h5 mb-3">Hodnocení a komentáře</h2>

            <?php if (!$commentsOpen): ?>
                <p class="text-muted mb-0">Hodnocení a komentáře se zobrazí po ukončení události.</p>
            <?php elseif (!$canFeedback): ?>
                <p class="text-muted mb-0">Hodnotit a komentovat mohou jen potvrzení účastníci po ukončení události.</p>
            <?php else: ?>
                <form method="post" action="<?= e(url('/udalosti/' . $event['slug'] . '/hodnoceni')) ?>" class="mb-4">
                    <?= csrf_field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-5">
                            <label for="value" class="form-label">Vaše hodnocení <span aria-hidden="true">*</span></label>
                            <select class="form-select" id="value" name="value" required>
                                <option value="">— vyberte —</option>
                                <?php for ($value = 1; $value <= 5; $value++): ?>
                                    <option value="<?= $value ?>"<?= selected($userRating['value'] ?? '', $value) ?>>
                                        <?= $value ?> / 5
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <button type="submit" class="btn btn-primary">Uložit hodnocení</button>
                        </div>
                    </div>
                    <div class="form-text">Hodnocení můžete kdykoli změnit.</div>
                </form>

                <?php if ($userComment !== null): ?>
                    <?php if ($userComment['status'] === 'hidden'): ?>
                        <div class="alert alert-warning py-2 small">
                            Váš komentář je skrytý moderátorem<?= !empty($userComment['hidden_reason']) ? ': ' . e($userComment['hidden_reason']) : '.' ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/komentar/' . $userComment['id'] . '/upravit')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label for="body" class="form-label">Váš komentář</label>
                            <textarea class="form-control" id="body" name="body" rows="4"
                                      required maxlength="2000"><?= e($userComment['body']) ?></textarea>
                            <div class="form-text">1–2000 znaků.</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">Uložit komentář</button>
                        </div>
                    </form>
                    <form method="post" action="<?= e(url('/komentar/' . $userComment['id'] . '/smazat')) ?>"
                          class="mt-2" data-confirm="Opravdu smazat svůj komentář?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">Smazat komentář</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/udalosti/' . $event['slug'] . '/komentar')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label for="body" class="form-label">
                                Váš komentář <span aria-hidden="true">*</span>
                            </label>
                            <textarea class="form-control" id="body" name="body" rows="4"
                                      required maxlength="2000"></textarea>
                            <div class="form-text">1–2000 znaků. Komentář můžete později upravit nebo smazat.</div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Přidat komentář</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="bg-white border rounded-3 p-4 mt-4" id="komentare">
            <h2 class="h5 mb-3">Komentáře účastníků</h2>
            <?php if ($comments === []): ?>
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
