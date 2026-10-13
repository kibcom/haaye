<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\TaskAccess;
use App\Services\TaskLifecycle;
use App\Services\TaskLifecycleService;
use App\Services\TaskService;

final class TaskController extends Controller
{
    public function index(): void
    {
        $this->listing(false);
    }

    public function mine(): void
    {
        $this->listing(true);
    }

    public function create(): void
    {
        $this->authorize('tasks.create');
        $orgId = $this->tenantId();
        TaskStatus::ensureDefaults($orgId);
        $projectId = (int) Request::input('project', 0);
        $projects = Project::optionsVisible($orgId, TaskAccess::restrictToUserId());
        $prefill = 0;
        if ($projectId > 0) {
            $project = Project::findInOrganization($projectId, $orgId);
            if ($project instanceof Project && TaskAccess::canCreate($project)) {
                $prefill = (int) $project->id;
            }
        }

        $this->view('tasks/form', [
            'title' => 'New task',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Tasks', 'url' => 'tasks'],
                ['label' => 'New task'],
            ],
            'task' => null,
            'projects' => $projects,
            'statuses' => TaskStatus::forOrganization($orgId),
            'people' => User::listAssignable($orgId),
            'prefillProject' => $prefill,
            'canAssign' => Auth::can('tasks.assign'),
        ]);
    }

    public function store(): void
    {
        $this->authorize('tasks.create');
        $data = $this->formData();
        if (!$this->validateTask($data, 'tasks/create')) {
            return;
        }

        try {
            $task = (new TaskService())->create($this->tenantId(), $data, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('tasks/create');
        }

        $this->clearOld();
        flash('success', 'Task has been created.');
        $this->redirect('tasks/' . $task->id);
    }

    public function show(string $id): void
    {
        $task = $this->visibleTask($id);
        $orgId = (int) $task->organization_id;
        $statuses = TaskStatus::forOrganization($orgId);
        [$nextStatus, $prevStatus] = \App\Services\StatusWorkflowService::taskNeighbors($orgId, (string) $task->status, $statuses);
        $autoProgress = TaskStatus::progressForSlug($statuses, (string) $task->status);
        $attachments = TaskAttachment::forTask((int) $task->id, $orgId);
        $filesByComment = [];
        $taskFiles = [];
        foreach ($attachments as $attachment) {
            if ($attachment->comment_id) {
                $filesByComment[(int) $attachment->comment_id][] = $attachment;
            } else {
                $taskFiles[] = $attachment;
            }
        }

        $autoTracking = false;
        $runningTimer = null;
        if (Auth::can('time.create') && \App\Services\FeatureService::allows('time')) {
            $tracked = (new \App\Services\TimeService())->autoTrack($orgId, [
                'project_id' => (int) $task->project_id,
                'task_id' => (int) $task->id,
                'billable' => '1',
            ], (int) Auth::id());
            $autoTracking = $tracked instanceof \App\Models\TimeEntry
                && (int) ($tracked->task_id ?? 0) === (int) $task->id;
            $runningTimer = $tracked;
        } elseif (Auth::can('time.view')) {
            $runningTimer = \App\Models\TimeEntry::runningFor($orgId, (int) Auth::id());
        }

        $this->view('tasks/show', [
            'title' => $task->title,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Tasks', 'url' => 'tasks'],
                ['label' => $task->title],
            ],
            'task' => $task,
            'statuses' => $statuses,
            'nextStatus' => $nextStatus,
            'prevStatus' => $prevStatus,
            'autoProgress' => $autoProgress,
            'comments' => TaskComment::forTask((int) $task->id, $orgId),
            'filesByComment' => $filesByComment,
            'taskFiles' => $taskFiles,
            'activity' => ActivityLog::recentForEntity($orgId, 'task', (int) $task->id),
            'canManage' => TaskAccess::canManage($task),
            'canChangeStatus' => TaskAccess::canChangeStatus($task),
            'canComment' => TaskAccess::canComment($task),
            'canUpload' => TaskAccess::canUpload($task),
            'pendingApprovals' => \App\Models\Approval::pendingForEntity($orgId, 'task', (int) $task->id),
            'timeEntries' => \App\Models\TimeEntry::forTask($orgId, (int) $task->id),
            'timeMinutes' => \App\Models\TimeEntry::minutesForTask($orgId, (int) $task->id),
            'runningTimer' => $runningTimer,
            'autoTracking' => $autoTracking,
            'canStartWork' => TaskAccess::canChangeStatus($task) && TaskLifecycle::canStart((string) $task->status),
            'canSubmitWork' => TaskLifecycle::canSubmit((string) $task->status)
                && (Auth::can('tasks.submit') || TaskAccess::canChangeStatus($task))
                && ((int) $task->assignee_user_id === (int) Auth::id() || Auth::can('tasks.update')),
            'pipelineStage' => $task->workflow_run_stage_id
                ? \App\Models\WorkflowRunStage::findByTask((int) $task->id, $orgId)
                : null,
        ]);
    }

    public function start(string $id): void
    {
        $task = $this->visibleTask($id);
        try {
            (new TaskLifecycleService())->start($task, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }
        flash('success', 'Task started. Complete the work, then submit for approval.');
        $this->redirect('tasks/' . $task->id);
    }

    public function submit(string $id): void
    {
        $task = $this->visibleTask($id);
        $note = trim((string) Request::input('submission_note', ''));
        try {
            $approval = (new TaskLifecycleService())->submit($task, (int) $this->actor()->id, $note);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }
        flash('success', 'Submitted for approval. Work is not approved until a manager decides.');
        $this->redirect('approvals/' . $approval->id);
    }

    public function edit(string $id): void
    {
        $task = $this->managedTask($id);
        $orgId = $this->tenantId();

        $this->view('tasks/form', [
            'title' => 'Edit task',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Tasks', 'url' => 'tasks'],
                ['label' => $task->title, 'url' => 'tasks/' . $task->id],
                ['label' => 'Edit'],
            ],
            'task' => $task,
            'projects' => Project::optionsVisible($orgId, TaskAccess::restrictToUserId()),
            'statuses' => TaskStatus::forOrganization($orgId),
            'people' => User::listAssignable($orgId),
            'prefillProject' => 0,
            'canAssign' => TaskAccess::canAssign($task),
        ]);
    }

    public function update(string $id): void
    {
        $task = $this->managedTask($id);
        $data = $this->formData();
        if (!$this->validateTask($data, 'tasks/' . $task->id . '/edit')) {
            return;
        }

        try {
            (new TaskService())->update($task, $data, $this->actor()->id);
        } catch (\App\Services\WorkflowPending $pending) {
            flash('success', 'Task saved. ' . $pending->getMessage());
            $this->redirect('tasks/' . $task->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id . '/edit');
        }

        $this->clearOld();
        flash('success', 'Task has been updated.');
        $this->redirect('tasks/' . $task->id);
    }

    public function move(string $id): void
    {
        $task = $this->visibleTask($id);
        if (!TaskAccess::canChangeStatus($task)) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => 'You cannot update this task.'], 403);
            }
            Response::forbidden();
        }

        $status = (string) Request::input('status', '');
        try {
            $updated = (new TaskService())->changeStatus($task, $status, null, $this->actor()->id);
        } catch (\App\Services\WorkflowPending $pending) {
            if ($this->wantsJson()) {
                $this->json(['ok' => true, 'pending' => true, 'message' => $pending->getMessage()]);
            }
            flash('success', $pending->getMessage());
            $this->back();
        } catch (\RuntimeException $e) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            flash('danger', $e->getMessage());
            $this->back();
        }

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'status' => $updated->status,
                'label' => $updated->status_name ?: $updated->status,
                'progress' => (int) $updated->progress,
            ]);
        }

        flash('success', 'Task status has been updated.');
        $this->back();
    }

    public function destroy(string $id): void
    {
        $task = $this->managedTask($id);
        try {
            (new TaskService())->delete($task, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('tasks/' . $task->id);
        }

        flash('success', 'Task has been removed.');
        $this->redirect('tasks');
    }

    private function listing(bool $mine): void
    {
        $this->authorize('tasks.view');
        \App\Services\Phase12Setup::applySchema();
        $orgId = $this->tenantId();
        TaskStatus::ensureDefaults($orgId);

        $search = (string) Request::input('q', '');
        $status = (string) Request::input('status', '');
        $priority = (string) Request::input('priority', '');
        $projectId = (int) Request::input('project_id', 0);
        $assigneeId = $mine ? (int) Auth::id() : (int) Request::input('assignee_id', 0);
        $view = Request::input('view') === 'board' ? 'board' : 'list';
        $onlyUserId = TaskAccess::restrictToUserId();

        $tasks = [];
        $pagination = null;
        if ($view === 'board') {
            $tasks = Task::board(
                $orgId,
                $search,
                $status,
                $priority,
                $projectId > 0 ? $projectId : null,
                $assigneeId > 0 ? $assigneeId : null,
                $onlyUserId
            );
        } else {
            $pagination = Task::paginate(
                $orgId,
                $this->page(),
                20,
                $search,
                $status,
                $priority,
                $projectId > 0 ? $projectId : null,
                $assigneeId > 0 ? $assigneeId : null,
                $onlyUserId
            );
            $tasks = $pagination['items'];
        }

        $this->view('tasks/index', [
            'title' => $mine ? 'My tasks' : 'Tasks',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Tasks', 'url' => 'tasks'],
                ['label' => $mine ? 'My tasks' : 'All tasks'],
            ],
            'mine' => $mine,
            'viewMode' => $view,
            'tasks' => $tasks,
            'pagination' => $pagination,
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'projectId' => $projectId,
            'assigneeId' => $mine ? 0 : $assigneeId,
            'projects' => Project::optionsVisible($orgId, $onlyUserId),
            'statuses' => TaskStatus::forOrganization($orgId),
            'people' => User::listAssignable($orgId),
        ]);
    }

    private function formData(): array
    {
        return Request::only([
            'title', 'description', 'project_id', 'assignee_user_id', 'priority',
            'status', 'start_date', 'due_date',
        ]);
    }

    private function validateTask(array $data, string $back): bool
    {
        $validator = new Validator($data);
        $ok = $validator->validate([
            'title' => 'required|max:180',
            'description' => 'max:5000',
            'project_id' => 'required',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|max:80',
            'start_date' => 'date',
            'due_date' => 'date',
        ]);

        if ($ok) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);
        return false;
    }

    private function visibleTask(string $id): Task
    {
        $this->authorize('tasks.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $task = Task::findInOrganization((int) $id, $this->tenantId());
        if (!$task instanceof Task || !TaskAccess::canView($task)) {
            Response::notFound();
        }

        return $task;
    }

    private function managedTask(string $id): Task
    {
        $task = $this->visibleTask($id);
        if (!TaskAccess::canManage($task)) {
            Response::forbidden();
        }

        return $task;
    }

    private function wantsJson(): bool
    {
        $accept = Request::header('Accept') ?? '';
        return Request::header('X-Requested-With') === 'XMLHttpRequest'
            || str_contains($accept, 'application/json');
    }

    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
