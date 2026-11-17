<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use App\Policies\CatalogPolicy;
use App\Policies\EventPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Event::class => EventPolicy::class,
        Venue::class => CatalogPolicy::class,
        Category::class => CatalogPolicy::class,
    ];

    public function boot(): void
    {
    }
}
