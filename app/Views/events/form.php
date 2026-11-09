<?php

declare(strict_types=1);

$firstError = array_key_first($errors);
$isEdit = $event !== null;
$canSubmit = $status === 'draft' || $status === 'rejected';
$canCancel = $isEdit && in_array($status, ['draft', 'pending', 'approved'], true);
$canSave = $status !== 'cancelled';
$badge = match ($status) {
    'approved' => 'success',
    'pending' => 'warning',
    'rejected' => 'danger',
    'cancelled' => 'secondary',
    default => 'secondary',
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h4 mb-0"><?= e($title) ?></h1>
    <div class="d-flex align-items-center gap-2">
        <?php if ($isEdit): ?>
            <span class="badge text-bg-<?= $badge ?>"><?= e(event_status_label($status)) ?></span>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('/moje-udalosti')) ?>">Zpět</a>
    </div>
</div>

<?php if ($isEdit && $status === 'rejected' && !empty($rejectionReason)): ?>
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Událost byla zamítnuta</div>
        <?= e($rejectionReason) ?>
    </div>
<?php endif; ?>

<?php if ($isEdit && $status === 'cancelled'): ?>
    <div class="alert alert-secondary">
        Událost je zrušená a nelze ji dále upravovat.<?= !empty($event['cancelled_reason']) ? ' Důvod: ' . e($event['cancelled_reason']) : '' ?>
    </div>
<?php endif; ?>

<?php if ($isEdit && $status === 'approved'): ?>
    <div class="alert alert-info">
        Podstatná změna schválené události (název, čas, místo, kategorie, kapacita nebo vstupenky)
        ji vrátí ke schválení. Popis a platební pokyny lze měnit bez nového schválení.
    </div>
<?php endif; ?>

<div class="bg-white border rounded-3 p-4">
    <?php require __DIR__ . '/../partials/form-errors.php'; ?>

    <form method="post" action="<?= e($action) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="title" class="form-label">
                Název události <span aria-hidden="true">*</span>
            </label>
            <input type="text" class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
                   id="title" name="title" value="<?= e(old('title', $formData['title'])) ?>"
                   required <?= $firstError === 'title' ? 'autofocus' : '' ?>
                   <?= isset($errors['title']) ? 'aria-invalid="true" aria-describedby="title-error"' : '' ?>>
            <?php if (isset($errors['title'])): ?>
                <div class="invalid-feedback d-block" id="title-error"><?= e($errors['title']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">
                Popis <span aria-hidden="true">*</span>
            </label>
            <textarea class="form-control<?= isset($errors['description']) ? ' is-invalid' : '' ?>"
                      id="description" name="description" rows="5" required
                      <?= $firstError === 'description' ? 'autofocus' : '' ?>
                      <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="description-error"' : '' ?>><?= e(old('description', $formData['description'])) ?></textarea>
            <div class="form-text">20–5000 znaků.</div>
            <?php if (isset($errors['description'])): ?>
                <div class="invalid-feedback d-block" id="description-error"><?= e($errors['description']) ?></div>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="starts_at" class="form-label">
                    Začátek <span aria-hidden="true">*</span>
                </label>
                <input type="datetime-local" class="form-control<?= isset($errors['starts_at']) ? ' is-invalid' : '' ?>"
                       id="starts_at" name="starts_at" value="<?= e(old('starts_at', $formData['starts_at'])) ?>"
                       required <?= $firstError === 'starts_at' ? 'autofocus' : '' ?>
                       <?= isset($errors['starts_at']) ? 'aria-invalid="true" aria-describedby="starts-at-error"' : '' ?>>
                <div class="form-text">Formát dd. mm. rrrr hh:mm.</div>
                <?php if (isset($errors['starts_at'])): ?>
                    <div class="invalid-feedback d-block" id="starts-at-error"><?= e($errors['starts_at']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-3">
                <label for="ends_at" class="form-label">
                    Konec <span aria-hidden="true">*</span>
                </label>
                <input type="datetime-local" class="form-control<?= isset($errors['ends_at']) ? ' is-invalid' : '' ?>"
                       id="ends_at" name="ends_at" value="<?= e(old('ends_at', $formData['ends_at'])) ?>"
                       required <?= $firstError === 'ends_at' ? 'autofocus' : '' ?>
                       <?= isset($errors['ends_at']) ? 'aria-invalid="true" aria-describedby="ends-at-error"' : '' ?>>
                <div class="form-text">Musí být po začátku.</div>
                <?php if (isset($errors['ends_at'])): ?>
                    <div class="invalid-feedback d-block" id="ends-at-error"><?= e($errors['ends_at']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="venue_id" class="form-label">
                    Místo konání <span aria-hidden="true">*</span>
                </label>
                <select class="form-select<?= isset($errors['venue_id']) ? ' is-invalid' : '' ?>"
                        id="venue_id" name="venue_id" required
                        <?= $firstError === 'venue_id' ? 'autofocus' : '' ?>
                        <?= isset($errors['venue_id']) ? 'aria-invalid="true" aria-describedby="venue-error"' : '' ?>>
                    <option value="">— vyberte místo —</option>
                    <?php foreach ($venueOptions as $venue): ?>
                        <option value="<?= (int) $venue['id'] ?>"<?= selected(old('venue_id', $formData['venue_id']), $venue['id']) ?>>
                            <?= e($venue['name']) ?> (<?= e($venue['city']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['venue_id'])): ?>
                    <div class="invalid-feedback d-block" id="venue-error"><?= e($errors['venue_id']) ?></div>
                <?php endif; ?>
                <div class="form-text">
                    Místo musí být schválené.
                    <a href="<?= e(url('/mista/navrhnout')) ?>" target="_blank" rel="noopener">Navrhnout nové místo</a>.
                </div>
            </div>
            <div class="col-md-5 mb-3">
                <label for="category_id" class="form-label">
                    Kategorie <span aria-hidden="true">*</span>
                </label>
                <select class="form-select<?= isset($errors['category_id']) ? ' is-invalid' : '' ?>"
                        id="category_id" name="category_id" required
                        <?= $firstError === 'category_id' ? 'autofocus' : '' ?>
                        <?= isset($errors['category_id']) ? 'aria-invalid="true" aria-describedby="category-error"' : '' ?>>
                    <option value="">— vyberte kategorii —</option>
                    <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"<?= selected(old('category_id', $formData['category_id']), $category['id']) ?>>
                            <?= str_repeat('— ', max(0, (int) $category['depth'] - 1)) . e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?>
                    <div class="invalid-feedback d-block" id="category-error"><?= e($errors['category_id']) ?></div>
                <?php endif; ?>
                <div class="form-text">
                    Kategorie musí být schválená.
                    <a href="<?= e(url('/kategorie/navrhnout')) ?>" target="_blank" rel="noopener">Navrhnout novou kategorii</a>.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="capacity" class="form-label">Kapacita (nepovinné)</label>
                <input type="number" class="form-control<?= isset($errors['capacity']) ? ' is-invalid' : '' ?>"
                       id="capacity" name="capacity" value="<?= e(old('capacity', $formData['capacity'])) ?>"
                       min="1" max="100000" inputmode="numeric"
                       <?= $firstError === 'capacity' ? 'autofocus' : '' ?>
                       <?= isset($errors['capacity']) ? 'aria-invalid="true" aria-describedby="capacity-error"' : '' ?>>
                <div class="form-text">Prázdné znamená neomezeno.</div>
                <?php if (isset($errors['capacity'])): ?>
                    <div class="invalid-feedback d-block" id="capacity-error"><?= e($errors['capacity']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-8 mb-3">
                <label for="payment_note" class="form-label">Platební pokyny (nepovinné)</label>
                <textarea class="form-control<?= isset($errors['payment_note']) ? ' is-invalid' : '' ?>"
                          id="payment_note" name="payment_note" rows="3"
                          <?= $firstError === 'payment_note' ? 'autofocus' : '' ?>
                          <?= isset($errors['payment_note']) ? 'aria-invalid="true" aria-describedby="payment-note-error"' : '' ?>><?= e(old('payment_note', $formData['payment_note'])) ?></textarea>
                <div class="form-text">Zobrazí se po registraci na placenou akci. Maximálně 1000 znaků.</div>
                <?php if (isset($errors['payment_note'])): ?>
                    <div class="invalid-feedback d-block" id="payment-note-error"><?= e($errors['payment_note']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="h5 mt-4 mb-2">Typy vstupenek</h2>
        <p class="text-muted small">
            Prázdný řádek se ignoruje. Vymazáním názvu u existujícího typu ho odstraníte;
            pokud už má registrace, odstranit ho nelze.
        </p>
        <?php if (isset($errors['tickets'])): ?>
            <div class="alert alert-danger"><?= e($errors['tickets']) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th scope="col">Název</th>
                    <th scope="col">Cena (Kč)</th>
                    <th scope="col">Kapacita typu</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ticketRows as $index => $row): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="ticket_id[]" value="<?= $row['id'] === null ? '' : (int) $row['id'] ?>">
                            <input type="text" class="form-control<?= isset($errors['ticket_name_' . $index]) ? ' is-invalid' : '' ?>"
                                   id="ticket_name_<?= $index ?>" name="ticket_name[]"
                                   value="<?= e($row['name']) ?>" maxlength="60"
                                   <?= isset($errors['ticket_name_' . $index]) ? 'aria-invalid="true" aria-describedby="ticket-name-' . $index . '-error"' : '' ?>>
                            <?php if (isset($errors['ticket_name_' . $index])): ?>
                                <div class="invalid-feedback d-block" id="ticket-name-<?= $index ?>-error">
                                    <?= e($errors['ticket_name_' . $index]) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" class="form-control<?= isset($errors['ticket_price_' . $index]) ? ' is-invalid' : '' ?>"
                                   id="ticket_price_<?= $index ?>" name="ticket_price[]"
                                   value="<?= e($row['price']) ?>" inputmode="decimal"
                                   <?= isset($errors['ticket_price_' . $index]) ? 'aria-invalid="true" aria-describedby="ticket-price-' . $index . '-error"' : '' ?>>
                            <div class="form-text">150 nebo 150,50</div>
                            <?php if (isset($errors['ticket_price_' . $index])): ?>
                                <div class="invalid-feedback d-block" id="ticket-price-<?= $index ?>-error">
                                    <?= e($errors['ticket_price_' . $index]) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" class="form-control<?= isset($errors['ticket_capacity_' . $index]) ? ' is-invalid' : '' ?>"
                                   id="ticket_capacity_<?= $index ?>" name="ticket_capacity[]"
                                   value="<?= $row['capacity'] === null ? '' : (int) $row['capacity'] ?>"
                                   min="1" inputmode="numeric"
                                   <?= isset($errors['ticket_capacity_' . $index]) ? 'aria-invalid="true" aria-describedby="ticket-capacity-' . $index . '-error"' : '' ?>>
                            <div class="form-text">Prázdné = bez limitu</div>
                            <?php if (isset($errors['ticket_capacity_' . $index])): ?>
                                <div class="invalid-feedback d-block" id="ticket-capacity-<?= $index ?>-error">
                                    <?= e($errors['ticket_capacity_' . $index]) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted small">* povinné pole</p>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($canSave): ?>
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">
                    <?= $isEdit ? 'Uložit změny' : 'Uložit koncept' ?>
                </button>
            <?php endif; ?>
            <?php if ($canSubmit || !$isEdit): ?>
                <button type="submit" name="action" value="submit" formaction="<?= e($submitAction) ?>" class="btn btn-primary">
                    Odeslat ke schválení
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($canCancel): ?>
    <div class="bg-white border rounded-3 p-4 mt-4">
        <h2 class="h5 mb-2">Zrušení události</h2>
        <p class="text-muted small">
            Zrušením se všechny aktivní registrace přepnou na zrušené. Akce je nevratná.
        </p>
        <form method="post" action="<?= e(url('/udalosti/' . $event['id'] . '/zrusit')) ?>"
              data-confirm="Opravdu zrušit událost <?= e($event['title']) ?>?">
            <?= csrf_field() ?>
            <div class="mb-2">
                <label for="reason" class="form-label">
                    Důvod zrušení <span aria-hidden="true">*</span>
                </label>
                <textarea class="form-control" id="reason" name="reason" rows="2" required minlength="3" maxlength="255"></textarea>
            </div>
            <button type="submit" class="btn btn-outline-danger">Zrušit událost</button>
        </form>
    </div>
<?php endif; ?>
