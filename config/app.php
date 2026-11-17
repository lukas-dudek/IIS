<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Události'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => 'cs',
    'fallback_locale' => 'en',
    'faker_locale' => 'cs_CZ',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'ics_host' => env('ICS_HOST', 'iis.local'),
    'maintenance' => [
        'driver' => 'file',
    ],
    'providers' => Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),
    'aliases' => Illuminate\Support\Facades\Facade::defaultAliases()->toArray(),
];
