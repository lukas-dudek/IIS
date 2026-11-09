<?php

declare(strict_types=1);

use App\Controllers\HomeController;

return [
    [
        'method' => 'GET',
        'pattern' => '/',
        'controller' => HomeController::class,
        'action' => 'index',
        'middleware' => [],
    ],
];
