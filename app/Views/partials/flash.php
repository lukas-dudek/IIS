<?php

declare(strict_types=1);

use App\Core\Flash;

foreach (Flash::pull() as $message): ?>
    <div class="alert alert-<?= e($message['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($message['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button>
    </div>
<?php endforeach; ?>
