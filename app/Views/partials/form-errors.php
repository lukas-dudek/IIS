<?php

declare(strict_types=1);
?>
<?php if (!empty($formError)): ?>
    <div class="alert alert-danger" role="alert"><?= e($formError) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-2">Zkontrolujte zadané údaje:</p>
        <ul class="mb-0">
            <?php foreach ($errors as $field => $message): ?>
                <li><a href="#<?= e($field) ?>"><?= e($message) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
