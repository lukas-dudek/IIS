<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $appName) ?> | <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-light">
<?php require __DIR__ . '/../partials/nav.php'; ?>
<main class="container py-4">
    <?php require __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
</main>
<footer class="border-top bg-white py-3 mt-5">
    <div class="container text-muted small">
        <?= e($appName) ?> · Informační systém FIT VUT
    </div>
</footer>
<script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
