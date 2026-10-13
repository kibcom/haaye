<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ProjectAccess;
use App\Services\TaskAccess;
use App\Services\TimeAccess;
use App\Services\TimeService;

final class TimeController extends Controller
{
    public function index(): void
    {
        $this->authorize('time.view');
        $orgId = $this->tenantId();
        [$from, $to, $label] = TimeService::weekBounds((string) Request::input('week', ''));
        $subject = $this->subjectUser($orgId);
        $entries = TimeEntry::forTimesheet(TimeAccess::scope($orgId), (int) $subject->id, $from . ' 00:00:00', $to . ' 00:00:00');
        $minutes = 0;
        $byDay = [];
        foreach ($entries as $entry) {
            $key = substr($entry->started_at, 0, 10);
            $byDay[$key][] = $entry;
            $minutes += (int) $entry->duration_minutes;
        }

        $this->view('time/index', [
            'title' => 'Timesheet',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Timesheet'],
            ],
            'subject' => $subject,
            'people' => Auth::can('time.view_all') ? User::listAssignable($orgId) : [],
            'weekStart' => $from,
            'weekLabel' => $label,
            'previousWeek' => (new \DateTimeImmutable($from))->modify('-7 days')->format('Y-m-d'),
            'nextWeek' => $to,
            'byDay' => $byDay,
            'minutes' => $minutes,
            'running' => TimeEntry::runningFor($orgId, (int) Auth::id()),
        ]);
    }

    public function create(): void
    {
        $this->authorize('time.create');
        $this->formView(null);
    }

    public function store(): void
    {
        $this->authorize('time.create');
        $data = $this->formData();
        if (!$this->validateEntry($data, 'time/log')) {
            return;
        }

        try {
            (new TimeService())->create($this->tenantId(), $data, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([], $data);
            $this->redirect($this->logReturn());
        }

        $this->clearOld();
        flash('success', 'Time has been logged.');
        $this->redirect($this->safeReturn());
    }

    public function edit(string $id): void
    {
        $entry = $this->visibleEntry($id);
        if (!TimeAccess::canEdit($entry)) {
            Response::forbidden();
        }
        $this->formView($entry);
    }

    public function update(string $id): void
    {
        $entry = $this->visibleEntry($id);
        if (!TimeAccess::canEdit($entry)) {
            Response::forbidden();
        }

        $data = $this->formData();
        if (!$this->validateEntry($data, 'time/' . $entry->id . '/edit')) {
            return;
        }

        try {
            (new TimeService())->update($entry, $data, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('time/' . $entry->id . '/edit');
        }

        $this->clearOld();
        flash('success', 'Time entry has been updated.');
        $this->redirect('time?week=' . substr($entry->started_at, 0, 10) . '&user=' . $entry->user_id);
    }

    public function destroy(string $id): void
    {
        $entry = $this->visibleEntry($id);
        if (!TimeAccess::canDelete($entry)) {
            Response::forbidden();
        }

        try {
            (new TimeService())->delete($entry, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('time');
        }

        flash('success', 'Time entry has been deleted.');
        $this->redirect('time?week=' . substr((string) $entry->started_at, 0, 10));
    }

    public function start(): void
    {
        $this->authorize('time.create');
        try {
            (new TimeService())->start($this->tenantId(), $this->formData(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect($this->safeReturn());
        }

        flash('success', 'Timer started.');
        $this->redirect($this->safeReturn());
    }

    public function stop(): void
    {
        $this->authorize('time.update');
        try {
            $entry = (new TimeService())->stop($this->tenantId(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect($this->safeReturn());
        }

        flash('success', 'Timer stopped. ' . format_minutes((int) $entry->duration_minutes) . ' logged.');
        $this->redirect($this->safeReturn());
    }

    public function workload(): void
    {
        $this->authorize('time.view_all');
        $orgId = $this->tenantId();
        [$from, $to, $label] = TimeService::weekBounds((string) Request::input('week', ''));
        $hours = TimeEntry::minutesByUser(TimeAccess::scope($orgId), $from . ' 00:00:00', $to . ' 00:00:00');
        $openTasks = Task::openCountsByAssignee($orgId, ProjectAccess::restrictToUserId());
        $rows = [];
        foreach (User::listAssignable($orgId) as $person) {
            $id = (int) $person->id;
            $rows[] = [
                'user' => $person,
                'open_tasks' => $openTasks[$id] ?? 0,
                'minutes' => $hours[$id]['minutes'] ?? 0,
                'billable_minutes' => $hours[$id]['billable_minutes'] ?? 0,
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            if ($a['open_tasks'] === $b['open_tasks']) {
                return $b['minutes'] <=> $a['minutes'];
            }

            return $b['open_tasks'] <=> $a['open_tasks'];
        });

        $this->view('time/workload', [
            'title' => 'Workload',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Timesheet', 'url' => 'time'],
                ['label' => 'Workload'],
            ],
            'rows' => $rows,
            'weekStart' => $from,
            'weekLabel' => $label,
            'previousWeek' => (new \DateTimeImmutable($from))->modify('-7 days')->format('Y-m-d'),
            'nextWeek' => $to,
        ]);
    }

    public function report(): void
    {
        $this->authorize('time.view');
        $orgId = $this->tenantId();
        [$defaultFrom] = TimeService::weekBounds(null);
        $from = $this->reportDate((string) Request::input('from', ''), $defaultFrom);
        $to = $this->reportDate((string) Request::input('to', ''), date('Y-m-d'));
        if ($to < $from) {
            $to = $from;
        }
        $span = (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;
        if ($span > 366) {
            $from = (new \DateTimeImmutable($to))->modify('-366 days')->format('Y-m-d');
        }

        $userId = Auth::can('time.view_all') ? (int) Request::input('user', 0) : (int) Auth::id();
        $projectId = (int) Request::input('project', 0);
        $scope = TimeAccess::scope($orgId);
        $exclusive = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        $start = $from . ' 00:00:00';
        $filterUser = $userId > 0 ? $userId : null;
        $filterProject = $projectId > 0 ? $projectId : null;

        $this->view('time/report', [
            'title' => 'Productivity',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Timesheet', 'url' => 'time'],
                ['label' => 'Productivity'],
            ],
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'projectId' => $projectId,
            'people' => Auth::can('time.view_all') ? User::listAssignable($orgId) : [],
            'projects' => Project::optionsVisible($orgId, ProjectAccess::restrictToUserId()),
            'byUser' => TimeEntry::grouped($scope, $start, $exclusive, 'user', $filterUser, $filterProject),
            'byProject' => TimeEntry::grouped($scope, $start, $exclusive, 'project', $filterUser, $filterProject),
            'byTask' => TimeEntry::grouped($scope, $start, $exclusive, 'task', $filterUser, $filterProject),
            'minutes' => TimeEntry::sumMinutes($scope, $start, $exclusive, $filterUser, $filterProject),
        ]);
    }

    private function formView(?TimeEntry $entry): void
    {
        $orgId = $this->tenantId();
        $this->view('time/form', [
            'title' => $entry ? 'Edit time' : 'Log time',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Timesheet', 'url' => 'time'],
                ['label' => $entry ? 'Edit' : 'Log time'],
            ],
            'entry' => $entry,
            'projects' => Project::optionsVisible($orgId, ProjectAccess::restrictToUserId()),
            'tasks' => Task::board($orgId, null, null, null, null, null, TaskAccess::restrictToUserId(), 300),
            'people' => Auth::can('time.manage') ? User::listAssignable($orgId) : [],
            'prefillProject' => $entry ? (int) $entry->project_id : (int) Request::input('project', 0),
            'prefillTask' => $entry ? (int) $entry->task_id : (int) Request::input('task', 0),
        ]);
    }

    private function visibleEntry(string $id): TimeEntry
    {
        $entry = TimeEntry::findInOrganization((int) $id, $this->tenantId());
        if (!$entry instanceof TimeEntry || !TimeAccess::canView($entry)) {
            Response::notFound();
        }

        return $entry;
    }

    private function subjectUser(int $organizationId): User
    {
        $requested = Auth::can('time.view_all') ? (int) Request::input('user', 0) : 0;
        if ($requested > 0) {
            $user = User::findInOrganization($requested, $organizationId);
            if ($user instanceof User && $user->role_slug !== 'client') {
                return $user;
            }
        }

        return $this->actor();
    }

    /** @param array<string, mixed> $data */
    private function validateEntry(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'project_id' => 'required',
            'work_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'note' => 'max:2000',
        ])) {
            return true;
        }

        $this->withErrors($validator->errors(), $data);
        $this->redirect($back);

        return false;
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return Request::only(['project_id', 'task_id', 'user_id', 'work_date', 'start_time', 'end_time', 'note', 'billable', 'return']);
    }

    private function logReturn(): string
    {
        $return = $this->safeReturn();
        if ($return === 'time') {
            return 'time/log';
        }

        return 'time/log?project=' . (int) Request::input('project_id', 0) . '&task=' . (int) Request::input('task_id', 0);
    }

    private function safeReturn(): string
    {
        $return = (string) Request::input('return', 'time');
        if ($return === 'time' || preg_match('#^tasks/[0-9]+$#', $return)) {
            return $return;
        }

        return 'time';
    }

    private function reportDate(string $value, string $fallback): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false) {
            return $value;
        }

        return $fallback;
    }
}
