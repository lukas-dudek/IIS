<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Události',
        'env' => 'local',
        'url' => 'http://127.0.0.1:8000',
        'base_path' => '',
        'timezone' => 'Europe/Prague',
        'debug' => true,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'iis',
        'user' => 'iis',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'iis_session',
        'timeout' => 1800,
        'secure' => false,
    ],
    'upload' => [
        'dir' => __DIR__ . '/../public/uploads',
        'max_bytes' => 2 * 1024 * 1024,
    ],
    'log' => [
        'dir' => __DIR__ . '/../storage/logs',
    ],
];
