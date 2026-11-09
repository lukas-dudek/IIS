<?php

declare(strict_types=1);

use App\Controllers\AdminUserController;
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
    [
        'method' => 'GET',
        'pattern' => '/admin/uzivatele',
        'controller' => AdminUserController::class,
        'action' => 'index',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'GET',
        'pattern' => '/admin/uzivatele/novy',
        'controller' => AdminUserController::class,
        'action' => 'create',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/admin/uzivatele/novy',
        'controller' => AdminUserController::class,
        'action' => 'create',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'GET',
        'pattern' => '/admin/uzivatele/{id}/upravit',
        'controller' => AdminUserController::class,
        'action' => 'edit',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/admin/uzivatele/{id}/upravit',
        'controller' => AdminUserController::class,
        'action' => 'edit',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/admin/uzivatele/{id}/heslo',
        'controller' => AdminUserController::class,
        'action' => 'resetPassword',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/admin/uzivatele/{id}/deaktivovat',
        'controller' => AdminUserController::class,
        'action' => 'deactivate',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/admin/uzivatele/{id}/aktivovat',
        'controller' => AdminUserController::class,
        'action' => 'activate',
        'middleware' => ['role:admin'],
    ],
    [
        'method' => 'POST',
        'pattern' => '/admin/uzivatele/{id}/smazat',
        'controller' => AdminUserController::class,
        'action' => 'delete',
        'middleware' => ['role:admin'],
    ],
];
