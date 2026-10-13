<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;

final class TimeService
{
    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public static function weekBounds(?string $anchor): array
    {
        $day = self::day($anchor) ?? new \DateTimeImmutable(date('Y-m-d'));
        $offset = (int) $day->format('N') - 1;
        $monday = $day->modify('-' . $offset . ' days');
        $next = $monday->modify('+7 days');
        $sunday = $monday->modify('+6 days');

        return [$monday->format('Y-m-d'), $next->format('Y-m-d'), $monday->format('M j') . ' – ' . $sunday->format('M j, Y')];
    }

    public function create(int $organizationId, array $input, int $actorId): TimeEntry
    {
        if (!Auth::can('time.create') && !Auth::can('time.manage')) {
            throw new \RuntimeException('You cannot log time.');
        }

        $userId = $this->subjectUserId($organizationId, $input, $actorId);
        [$project, $task] = $this->target($organizationId, $input);
        [$start, $end, $minutes] = $this->range($input);
        $now = date('Y-m-d H:i:s');

        $id = TimeEntry::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'project_id' => (int) $project->id,
            'task_id' => $task ? (int) $task->id : null,
            'client_id' => (int) $project->client_id,
            'started_at' => $start->format('Y-m-d H:i:s'),
            'ended_at' => $end->format('Y-m-d H:i:s'),
            'duration_minutes' => $minutes,
            'note' => $this->note($input),
            'billable' => $this->billable($input),
            'source' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'time.logged', $this->describe($minutes, $project, $task), 'time_entry', $id);

        $entry = TimeEntry::findInOrganization($id, $organizationId);
        if (!$entry instanceof TimeEntry) {
            throw new \RuntimeException('Time entry could not be saved.');
        }

        return $entry;
    }

    public function update(TimeEntry $entry, array $input, int $actorId): void
    {
        if (!TimeAccess::canEdit($entry)) {
            throw new \RuntimeException('You cannot edit this time entry.');
        }

        [$project, $task] = $this->target((int) $entry->organization_id, $input);
        [$start, $end, $minutes] = $this->range($input);

        TimeEntry::updateById((int) $entry->id, (int) $entry->organization_id, [
            'project_id' => (int) $project->id,
            'task_id' => $task ? (int) $task->id : null,
            'client_id' => (int) $project->client_id,
            'started_at' => $start->format('Y-m-d H:i:s'),
            'ended_at' => $end->format('Y-m-d H:i:s'),
            'duration_minutes' => $minutes,
            'note' => $this->note($input),
            'billable' => $this->billable($input),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record((int) $entry->organization_id, $actorId, 'time.updated', $this->describe($minutes, $project, $task), 'time_entry', (int) $entry->id);
    }

    public function delete(TimeEntry $entry, int $actorId): void
    {
        if (!TimeAccess::canDelete($entry)) {
            throw new \RuntimeException('You cannot delete this time entry.');
        }

        TimeEntry::deleteById((int) $entry->id, (int) $entry->organization_id);
        ActivityLog::record(
            (int) $entry->organization_id,
            $actorId,
            'time.deleted',
            $entry->isRunning() ? 'Cancelled a running timer.' : 'Deleted a time entry.',
            'time_entry',
            (int) $entry->id
        );
    }

    public function start(int $organizationId, array $input, int $actorId): TimeEntry
    {
        if (!Auth::can('time.create')) {
            throw new \RuntimeException('You cannot start a timer.');
        }

        if (TimeEntry::runningFor($organizationId, $actorId) instanceof TimeEntry) {
            throw new \RuntimeException('Stop the timer that is already running.');
        }

        return $this->beginTimer($organizationId, $input, $actorId, false);
    }

    /**
     * Start (or keep) a timer for the page the user opened.
     * Switches away from another running timer; discards visits under one minute.
     */
    public function autoTrack(int $organizationId, array $input, int $actorId): ?TimeEntry
    {
        if (!Auth::can('time.create')) {
            return null;
        }

        try {
            [$project, $task] = $this->target($organizationId, $input);
        } catch (\RuntimeException) {
            return null;
        }

        $running = TimeEntry::runningFor($organizationId, $actorId);
        if ($running instanceof TimeEntry) {
            $sameProject = (int) $running->project_id === (int) $project->id;
            $sameTask = $task instanceof Task
                ? (int) ($running->task_id ?? 0) === (int) $task->id
                : $running->task_id === null;

            if ($sameProject && $sameTask) {
                return $running;
            }

            // Opening a project while already on one of its tasks: keep the task timer.
            if (!$task instanceof Task && $sameProject && $running->task_id !== null) {
                return $running;
            }

            $this->stopOrDiscardBrief($organizationId, $actorId);
        }

        try {
            return $this->beginTimer($organizationId, [
                'project_id' => (int) $project->id,
                'task_id' => $task instanceof Task ? (int) $task->id : 0,
                'note' => '',
                'billable' => '1',
            ], $actorId, true);
        } catch (\RuntimeException) {
            return null;
        }
    }

    public function stop(int $organizationId, int $actorId): TimeEntry
    {
        $entry = TimeEntry::runningFor($organizationId, $actorId);
        if (!$entry instanceof TimeEntry) {
            throw new \RuntimeException('There is no timer running.');
        }

        $start = new \DateTimeImmutable($entry->started_at);
        $end = new \DateTimeImmutable();
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        if ($seconds < 60) {
            $end = $start->modify('+1 minute');
            $minutes = 1;
        } else {
            $minutes = (int) round($seconds / 60);
        }

        TimeEntry::updateById((int) $entry->id, $organizationId, [
            'ended_at' => $end->format('Y-m-d H:i:s'),
            'duration_minutes' => $minutes,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record($organizationId, $actorId, 'time.stopped', 'Stopped a timer after ' . format_minutes($minutes) . '.', 'time_entry', (int) $entry->id);
        $saved = TimeEntry::findInOrganization((int) $entry->id, $organizationId);
        if (!$saved instanceof TimeEntry) {
            throw new \RuntimeException('Timer could not be stopped.');
        }

        return $saved;
    }

    private function beginTimer(int $organizationId, array $input, int $actorId, bool $auto): TimeEntry
    {
        [$project, $task] = $this->target($organizationId, $input);
        $now = date('Y-m-d H:i:s');
        $id = TimeEntry::create([
            'organization_id' => $organizationId,
            'user_id' => $actorId,
            'project_id' => (int) $project->id,
            'task_id' => $task ? (int) $task->id : null,
            'client_id' => (int) $project->client_id,
            'started_at' => $now,
            'ended_at' => null,
            'duration_minutes' => null,
            'note' => $this->note($input),
            'billable' => $this->billable($input),
            'source' => $auto ? 'auto' : 'timer',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $label = $task ? $task->title : $project->name;
        ActivityLog::record(
            $organizationId,
            $actorId,
            $auto ? 'time.auto_started' : 'time.started',
            ($auto ? 'Auto-started timer on ' : 'Started a timer on ') . $label . '.',
            'time_entry',
            $id
        );
        $entry = TimeEntry::findInOrganization($id, $organizationId);
        if (!$entry instanceof TimeEntry) {
            throw new \RuntimeException('Timer could not be started.');
        }

        return $entry;
    }

    private function stopOrDiscardBrief(int $organizationId, int $actorId): void
    {
        $entry = TimeEntry::runningFor($organizationId, $actorId);
        if (!$entry instanceof TimeEntry) {
            return;
        }

        $start = new \DateTimeImmutable($entry->started_at);
        $seconds = (new \DateTimeImmutable())->getTimestamp() - $start->getTimestamp();
        if ($seconds < 60) {
            TimeEntry::deleteById((int) $entry->id, $organizationId);
            return;
        }

        $this->stop($organizationId, $actorId);
    }

    private function subjectUserId(int $organizationId, array $input, int $actorId): int
    {
        $requested = (int) ($input['user_id'] ?? 0);
        if ($requested === 0 || $requested === $actorId) {
            return $actorId;
        }

        if (!Auth::can('time.manage')) {
            throw new \RuntimeException('You can only log time for yourself.');
        }

        $user = User::findInOrganization($requested, $organizationId);
        if (!$user instanceof User || $user->role_slug === 'client' || $user->status !== 'active') {
            throw new \RuntimeException('Choose an active teammate.');
        }

        return $requested;
    }

    /**
     * @return array{0: Project, 1: ?Task}
     */
    private function target(int $organizationId, array $input): array
    {
        $project = Project::findInOrganization((int) ($input['project_id'] ?? 0), $organizationId);
        if (!$project instanceof Project || !ProjectAccess::canView($project)) {
            throw new \RuntimeException('Choose a project you can see.');
        }

        $taskId = (int) ($input['task_id'] ?? 0);
        if ($taskId === 0) {
            return [$project, null];
        }

        $task = Task::findInOrganization($taskId, $organizationId);
        if (!$task instanceof Task || (int) $task->project_id !== (int) $project->id || !TaskAccess::canView($task)) {
            throw new \RuntimeException('That task is not on the selected project.');
        }

        return [$project, $task];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: int}
     */
    private function range(array $input): array
    {
        $date = trim((string) ($input['work_date'] ?? ''));
        $start = $this->clock($date, (string) ($input['start_time'] ?? ''));
        $end = $this->clock($date, (string) ($input['end_time'] ?? ''));
        if ($end <= $start) {
            throw new \RuntimeException('End time must be later than the start time on the same day.');
        }

        $minutes = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($minutes < 1) {
            throw new \RuntimeException('Time entries must be at least one minute.');
        }
        if ($minutes > 24 * 60) {
            throw new \RuntimeException('A single entry cannot be longer than 24 hours.');
        }

        return [$start, $end, $minutes];
    }

    private function clock(string $date, string $time): \DateTimeImmutable
    {
        $time = trim($time);
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        $value = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time);
        $errors = \DateTimeImmutable::getLastErrors();
        $invalid = !$value || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0));
        if ($invalid) {
            throw new \RuntimeException('Enter a valid date and time.');
        }

        return $value;
    }

    private function note(array $input): ?string
    {
        $note = trim((string) ($input['note'] ?? ''));
        if ($note === '') {
            return null;
        }
        if (strlen($note) > 2000) {
            throw new \RuntimeException('Notes may not be longer than 2000 characters.');
        }

        return $note;
    }

    private function billable(array $input): int
    {
        return (string) ($input['billable'] ?? '0') === '1' ? 1 : 0;
    }

    private function describe(int $minutes, Project $project, ?Task $task): string
    {
        $label = $task ? $task->title : $project->name;

        return 'Logged ' . format_minutes($minutes) . ' on ' . $label . '.';
    }

    private static function day(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$day || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            return null;
        }

        return $day;
    }
}
