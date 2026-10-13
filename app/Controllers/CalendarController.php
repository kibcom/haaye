<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Task;
use App\Services\EthiopianCalendar;
use App\Services\TaskAccess;

final class CalendarController extends Controller
{
    public function index(): void
    {
        $this->authorize('calendar.view');
        $orgId = $this->tenantId();
        $cal = Request::input('cal') === 'eth' ? 'eth' : 'greg';
        $viewMode = Request::input('view');
        $viewMode = in_array($viewMode, ['week', 'day'], true) ? $viewMode : 'month';
        $focusDate = (string) Request::input('day', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $focusDate)) {
            $focusDate = date('Y-m-d');
        }

        if ($cal === 'eth') {
            $payload = $this->ethiopianMonth($orgId);
        } else {
            $payload = $this->gregorianMonth($orgId);
        }

        $this->view('calendar/index', array_merge([
            'title' => 'Calendar',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Calendar'],
            ],
            'cal' => $cal,
            'viewMode' => $viewMode,
            'focusDate' => $focusDate,
        ], $payload, $this->rail($orgId)));
    }

    private function gregorianMonth(int $orgId): array
    {
        $monthInput = $this->resolveMonthInput(12);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
            $monthInput = date('Y-m');
        }

        try {
            $monthStart = new \DateTimeImmutable($monthInput . '-01');
        } catch (\Exception $e) {
            $monthStart = new \DateTimeImmutable('first day of this month');
        }

        $dow = (int) $monthStart->format('N');
        $gridStart = $monthStart->modify('-' . ($dow - 1) . ' days');
        $gridEnd = $gridStart->modify('+41 days');
        $taskCount = 0;
        $weeks = $this->buildWeeks($orgId, $gridStart, $gridEnd, static function (\DateTimeImmutable $day) use ($monthStart): array {
            $eth = EthiopianCalendar::fromDate($day);

            return [
                'day' => (int) $day->format('j'),
                'sub' => $eth['day'] . ' ' . (EthiopianCalendar::MONTHS[$eth['month']] ?? ''),
                'in_month' => $day->format('Y-m') === $monthStart->format('Y-m'),
            ];
        }, $taskCount);

        return [
            'monthLabel' => $monthStart->format('F Y'),
            'monthKey' => $monthStart->format('Y-m'),
            'filterYear' => (int) $monthStart->format('Y'),
            'filterMonth' => (int) $monthStart->format('n'),
            'yearOptions' => $this->yearOptions((int) $monthStart->format('Y'), 1990, (int) date('Y') + 5),
            'monthOptions' => [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
            ],
            'previousMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'thisMonthUrl' => 'calendar',
            'weeks' => $weeks,
            'taskCount' => $taskCount,
            'weekdayLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    private function ethiopianMonth(int $orgId): array
    {
        $monthInput = $this->resolveMonthInput(13);
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-3])$/', $monthInput, $match)) {
            $ethYear = (int) $match[1];
            $ethMonth = (int) $match[2];
        } else {
            $today = EthiopianCalendar::fromDate(new \DateTimeImmutable('today'));
            $ethYear = $today['year'];
            $ethMonth = $today['month'];
        }

        $daysInMonth = EthiopianCalendar::daysInMonth($ethYear, $ethMonth);
        if ($daysInMonth < 1) {
            $today = EthiopianCalendar::fromDate(new \DateTimeImmutable('today'));
            $ethYear = $today['year'];
            $ethMonth = $today['month'];
            $daysInMonth = EthiopianCalendar::daysInMonth($ethYear, $ethMonth);
        }

        $monthStart = EthiopianCalendar::toGregorianDate($ethYear, $ethMonth, 1);
        $monthEnd = EthiopianCalendar::toGregorianDate($ethYear, $ethMonth, $daysInMonth);
        $dow = (int) $monthStart->format('N');
        $gridStart = $monthStart->modify('-' . ($dow - 1) . ' days');
        $gridEnd = $gridStart->modify('+41 days');
        if ($monthEnd > $gridEnd) {
            $gridEnd = $monthEnd;
        }

        $prev = EthiopianCalendar::shiftMonth($ethYear, $ethMonth, -1);
        $next = EthiopianCalendar::shiftMonth($ethYear, $ethMonth, 1);
        $now = EthiopianCalendar::fromDate(new \DateTimeImmutable('today'));
        $taskCount = 0;
        $weeks = $this->buildWeeks($orgId, $gridStart, $gridEnd, static function (\DateTimeImmutable $day) use ($ethYear, $ethMonth): array {
            $eth = EthiopianCalendar::fromDate($day);

            return [
                'day' => $eth['day'],
                'sub' => $day->format('M j'),
                'in_month' => $eth['year'] === $ethYear && $eth['month'] === $ethMonth,
            ];
        }, $taskCount);

        return [
            'monthLabel' => EthiopianCalendar::monthLabel($ethYear, $ethMonth) . ' E.C.',
            'monthKey' => sprintf('%04d-%02d', $ethYear, $ethMonth),
            'filterYear' => $ethYear,
            'filterMonth' => $ethMonth,
            'yearOptions' => $this->yearOptions($ethYear, 1980, $ethYear + 5),
            'monthOptions' => EthiopianCalendar::MONTHS,
            'previousMonth' => sprintf('%04d-%02d', $prev['year'], $prev['month']),
            'nextMonth' => sprintf('%04d-%02d', $next['year'], $next['month']),
            'thisMonthUrl' => 'calendar?cal=eth&month=' . sprintf('%04d-%02d', $now['year'], $now['month']),
            'weeks' => $weeks,
            'taskCount' => $taskCount,
            'weekdayLabels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    private function resolveMonthInput(int $maxMonth): string
    {
        $year = (int) Request::input('year', 0);
        $monthNum = (int) Request::input('m', 0);
        if ($year >= 1900 && $year <= 2200 && $monthNum >= 1 && $monthNum <= $maxMonth) {
            return sprintf('%04d-%02d', $year, $monthNum);
        }

        return (string) Request::input('month', '');
    }

    /** @return array<int,int> */
    private function yearOptions(int $current, int $min, int $max): array
    {
        $start = min($min, $current - 10);
        $end = max($max, $current + 5);
        $years = [];
        for ($year = $end; $year >= $start; $year--) {
            $years[$year] = $year;
        }

        return $years;
    }

    /**
     * @param callable(\DateTimeImmutable):array{day:int,sub:string,in_month:bool} $decorate
     * @return list<list<array<string,mixed>>>
     */
    private function buildWeeks(int $orgId, \DateTimeImmutable $gridStart, \DateTimeImmutable $gridEnd, callable $decorate, ?int &$taskCount): array
    {
        $tasks = Task::forCalendar($orgId, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
        $taskCount = count($tasks);
        $byDay = [];
        foreach ($tasks as $task) {
            if ($task->due_date === null || $task->due_date === '') {
                continue;
            }
            $key = substr($task->due_date, 0, 10);
            $byDay[$key][] = $task;
        }

        $weeks = [];
        $day = $gridStart;
        for ($w = 0; $w < 6; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $key = $day->format('Y-m-d');
                $meta = $decorate($day);
                $items = [];
                foreach ($byDay[$key] ?? [] as $task) {
                    $items[] = [
                        'task' => $task,
                        'can_open' => TaskAccess::canView($task),
                    ];
                }
                $week[] = [
                    'date' => $key,
                    'day' => $meta['day'],
                    'sub' => $meta['sub'],
                    'in_month' => $meta['in_month'],
                    'is_today' => $key === date('Y-m-d'),
                    'tasks' => $items,
                ];
                $day = $day->modify('+1 day');
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /** @return array{todayItems:list<array<string,mixed>>,upcoming:list<array<string,mixed>>} */
    private function rail(int $orgId): array
    {
        $today = new \DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');
        $tasks = Task::forCalendar(
            $orgId,
            $today->modify('-21 days')->format('Y-m-d'),
            $today->modify('+60 days')->format('Y-m-d')
        );
        $todayItems = [];
        $upcoming = [];
        foreach ($tasks as $task) {
            $due = substr((string) $task->due_date, 0, 10);
            $item = [
                'task' => $task,
                'can_open' => TaskAccess::canView($task),
                'due' => $due,
            ];
            if ($due === $todayKey) {
                $todayItems[] = $item;
            } elseif ($due > $todayKey) {
                $upcoming[] = $item;
            }
        }

        return [
            'todayItems' => $todayItems,
            'upcoming' => array_slice($upcoming, 0, 5),
        ];
    }
}
