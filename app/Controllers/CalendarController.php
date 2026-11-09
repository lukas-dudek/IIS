<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\EventRepository;
use DateTimeImmutable;
use DateTimeZone;

final class CalendarController
{
    public function __construct(
        private View $view,
        private Auth $auth,
        private EventRepository $events,
        private Config $config
    ) {
    }

    public function index(Request $request, array $params): Response
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $month = $this->parseMonth($request->query('mesic', ''), $now);

        $monthStart = $month->setTime(0, 0, 0);
        $monthEnd = $month
            ->setDate((int) $month->format('Y'), (int) $month->format('n'), (int) $month->format('t'))
            ->setTime(23, 59, 59);
        $fromUtc = $monthStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = $monthEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $events = $this->events->calendarForUser((int) $this->auth->id(), $fromUtc, $toUtc);
        $eventsByDay = [];
        foreach ($events as $event) {
            $local = utc_to_prague((string) $event['starts_at']);
            if ($local !== null) {
                $eventsByDay[(int) $local->format('j')][] = $event;
            }
        }

        return Response::html($this->view->render('calendar/index', [
            'title' => 'Můj kalendář',
            'month' => $month,
            'monthLabel' => czech_month_name((int) $month->format('n')) . ' ' . $month->format('Y'),
            'previousMonth' => $month->modify('-1 month')->format('Y-m'),
            'nextMonth' => $month->modify('+1 month')->format('Y-m'),
            'currentMonth' => $now->format('Y-m'),
            'todayDay' => (int) $now->format('j'),
            'daysInMonth' => (int) $month->format('t'),
            'startWeekday' => (int) $month->format('N'),
            'eventsByDay' => $eventsByDay,
            'events' => $events,
        ]));
    }

    public function export(Request $request, array $params): Response
    {
        $events = $this->events->exportForUser((int) $this->auth->id());
        $ics = build_ics(
            $events,
            (string) $this->config->get('app.ics_host', 'iis.local'),
            (string) $this->config->get('app.url', '')
        );

        return new Response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="moje-udalosti.ics"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function parseMonth(mixed $value, DateTimeImmutable $now): DateTimeImmutable
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})$/', $value, $matches) === 1) {
            $monthNumber = (int) $matches[2];
            if ($monthNumber >= 1 && $monthNumber <= 12) {
                $date = DateTimeImmutable::createFromFormat(
                    '!Y-n-j',
                    $matches[1] . '-' . $monthNumber . '-1',
                    new DateTimeZone('Europe/Prague')
                );
                if ($date !== false) {
                    return $date;
                }
            }
        }

        return $now->setDate((int) $now->format('Y'), (int) $now->format('n'), 1)->setTime(0, 0, 0);
    }
}
