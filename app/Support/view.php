<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Venue;
use Carbon\CarbonInterface;

if (!function_exists('event_status_label')) {
    function event_status_label(string $status): string
    {
        return (string) __('domain.status.event.' . $status);
    }
}

if (!function_exists('catalog_status_label')) {
    function catalog_status_label(string $status): string
    {
        return (string) __('domain.status.catalog.' . $status);
    }
}

if (!function_exists('registration_status_label')) {
    function registration_status_label(string $status): string
    {
        return (string) __('domain.status.registration.' . $status);
    }
}

if (!function_exists('comment_status_label')) {
    function comment_status_label(string $status): string
    {
        return (string) __('domain.status.comment.' . $status);
    }
}

if (!function_exists('role_label')) {
    function role_label(string $role): string
    {
        return (string) __('domain.role.' . $role);
    }
}

if (!function_exists('status_badge_class')) {
    function status_badge_class(string $status): string
    {
        return match ($status) {
            Event::STATUS_APPROVED, Venue::STATUS_APPROVED, Category::STATUS_APPROVED,
            Registration::STATUS_CONFIRMED, 'visible' => 'text-bg-success',
            Event::STATUS_PENDING, Venue::STATUS_PENDING, Category::STATUS_PENDING,
            Registration::STATUS_PENDING => 'text-bg-warning',
            Event::STATUS_REJECTED, Venue::STATUS_REJECTED, Category::STATUS_REJECTED,
            Registration::STATUS_CANCELLED, 'hidden' => 'text-bg-danger',
            Event::STATUS_CANCELLED => 'text-bg-secondary',
            default => 'text-bg-secondary',
        };
    }
}

if (!function_exists('prague_input_value')) {
    function prague_input_value(?CarbonInterface $moment): string
    {
        return $moment === null ? '' : $moment->setTimezone('Europe/Prague')->format('Y-m-d\TH:i');
    }
}
