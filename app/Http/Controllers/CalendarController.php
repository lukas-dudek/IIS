<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\EventRepository;
use App\Support\IcsExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class CalendarController extends Controller
{
    public function __construct(private EventRepository $events)
    {
    }

    public function index(Request $request): View
    {
        $month = $this->parseMonth((string) $request->query('mesic', ''));

        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();

        $events = $this->events->forCalendar($request->user(), $monthStart, $monthEnd);

        $byDay = [];
        foreach ($events as $event) {
            $day = (int) $event->starts_at->setTimezone('Europe/Prague')->format('j');
            $byDay[$day][] = $event;
        }

        return view('calendar.index', [
            'monthLabel' => czech_month_name((int) $month->format('n')) . ' ' . $month->format('Y'),
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
            'isCurrentMonth' => $month->format('Y-m') === prague_now()->format('Y-m'),
            'today' => (int) prague_now()->format('j'),
            'weeks' => $this->weeks($month),
            'eventsByDay' => $byDay,
            'events' => $events,
        ]);
    }

    public function export(Request $request): Response
    {
        $ics = IcsExport::build(
            $this->events->exportable($request->user()),
            (string) config('app.ics_host', 'iis.local'),
            (string) config('app.url')
        );

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="moje-udalosti.ics"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Mřížka měsíce po týdnech; null znamená prázdnou buňku mimo měsíc.
     *
     * @return array<int, array<int, ?int>>
     */
    private function weeks(CarbonImmutable $month): array
    {
        $cells = array_fill(0, $month->dayOfWeekIso - 1, null);
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $cells[] = $day;
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }

    private function parseMonth(string $value): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches) === 1) {
            $month = (int) $matches[2];
            if ($month >= 1 && $month <= 12) {
                return CarbonImmutable::createFromFormat('!Y-n-j', $matches[1] . '-' . $month . '-1', 'Europe/Prague')
                    ->startOfDay();
            }
        }

        return prague_now()->startOfMonth()->startOfDay();
    }
}
