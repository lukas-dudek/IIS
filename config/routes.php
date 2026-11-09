<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;

return [
    [
        'method' => 'GET',
        'pattern' => '/',
        'controller' => HomeController::class,
        'action' => 'index',
        'middleware' => [],
    ],
    [
        'method' => 'GET',
        'pattern' => '/prihlaseni',
        'controller' => AuthController::class,
        'action' => 'showLogin',
        'middleware' => [],
    ],
    [
        'method' => 'POST',
        'pattern' => '/prihlaseni',
        'controller' => AuthController::class,
        'action' => 'login',
        'middleware' => [],
    ],
    [
        'method' => 'GET',
        'pattern' => '/registrace',
        'controller' => AuthController::class,
        'action' => 'showRegister',
        'middleware' => [],
    ],
    [
        'method' => 'POST',
        'pattern' => '/registrace',
        'controller' => AuthController::class,
        'action' => 'register',
        'middleware' => [],
    ],
    [
        'method' => 'POST',
        'pattern' => '/odhlaseni',
        'controller' => AuthController::class,
        'action' => 'logout',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'GET',
        'pattern' => '/profil',
        'controller' => ProfileController::class,
        'action' => 'edit',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/profil',
        'controller' => ProfileController::class,
        'action' => 'update',
        'middleware' => ['auth'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/profil/heslo',
        'controller' => ProfileController::class,
        'action' => 'password',
        'middleware' => ['auth'],
    ],
];
