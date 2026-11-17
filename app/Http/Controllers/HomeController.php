<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function index(): View
    {
        return view('home.index', [
            'events' => Event::query()
                ->with(['venue', 'category', 'ticketTypes'])
                ->approved()
                ->upcoming()
                ->orderBy('starts_at')
                ->limit(6)
                ->get(),
            'eventCount' => Event::query()->approved()->upcoming()->count(),
            'venueCount' => Venue::query()->approved()->count(),
            'commentCount' => Comment::query()->visible()->count(),
        ]);
    }
}
