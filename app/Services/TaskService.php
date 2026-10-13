<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\FileUploader;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\WorkflowPending;
use App\Services\StatusWorkflowService;

final class TaskService
{
    public function create(int $organizationId, array $data, int $actorId): Task
    {
        $project = $this->project($organizationId, (int) ($data['project_id'] ?? 0));
        if (!TaskAccess::canCreate($project)) {
            throw new \RuntimeException('You cannot add tasks to this project.');
        }

        $status = $this->status($organizationId, (string) ($data['status'] ?? ''));
        $this->assertDates($data['start_date'] ?? null, $data['due_date'] ?? null);
        $assigneeId = TaskAccess::canAssignOnProject($project)
            ? $this->assignee($organizationId, $data['assignee_user_id'] ?? null)
            : null;
        $progress = TaskStatus::progressForSlug(TaskStatus::forOrganization($organizationId), $status->slug);

        $now = date('Y-m-d H:i:s');
        $id = Task::create([
            'organization_id' => $organizationId,
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'title' => $data['title'],
            'description' => $this->nullableText($data['description'] ?? null),
            'assignee_user_id' => $assigneeId,
            'reporter_user_id' => $actorId,
            'priority' => $data['priority'],
            'status' => $status->slug,
            'start_date' => $this->nullableText($data['start_date'] ?? null),
            'due_date' => $this->nullableText($data['due_date'] ?? null),
            'progress' => $progress,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'task.created', 'Task "' . $data['title'] . '" was created', 'task', $id);
        Project::syncProgress($organizationId, (int) $project->id);

        $task = Task::findInOrganization($id, $organizationId);
        if (!$task instanceof Task) {
            throw new \RuntimeException('Task could not be created.');
        }

        if ($assigneeId !== null && $assigneeId > 0) {
            NotificationService::notifyUser(
                $organizationId,
                $assigneeId,
                'Task assigned: ' . $task->title,
                ($task->project_name ?: 'Project') . ($task->due_date ? ' · Due ' . $task->due_date : ''),
                'tasks/' . (int) $task->id,
                $actorId
            );
        }

        return $task;
    }

    public function update(Task $task, array $data, int $actorId): void
    {
        if (!TaskAccess::canManage($task)) {
            throw new \RuntimeException('You cannot edit this task.');
        }

        $organizationId = (int) $task->organization_id;
        $project = $this->project($organizationId, (int) ($data['project_id'] ?? $task->project_id));
        if ((int) $project->id !== (int) $task->project_id && !ProjectAccess::canView($project)) {
            throw new \RuntimeException('Choose a project you can access.');
        }

        $status = $this->status($organizationId, (string) ($data['status'] ?? ''));
        $pending = $this->gateStatus($task, $status->slug, $actorId);
        $this->assertDates($data['start_date'] ?? null, $data['due_date'] ?? null);
        $assigneeId = TaskAccess::canAssign($task)
            ? $this->assignee($organizationId, $data['assignee_user_id'] ?? null)
            : $task->assignee_user_id;
        $effectiveSlug = $pending instanceof WorkflowPending ? (string) $task->status : $status->slug;
        $progress = TaskStatus::progressForSlug(TaskStatus::forOrganization($organizationId), $effectiveSlug);

        Task::updateById((int) $task->id, $organizationId, [
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'title' => $data['title'],
            'description' => $this->nullableText($data['description'] ?? null),
            'assignee_user_id' => $assigneeId,
            'priority' => $data['priority'],
            'status' => $effectiveSlug,
            'start_date' => $this->nullableText($data['start_date'] ?? null),
            'due_date' => $this->nullableText($data['due_date'] ?? null),
            'progress' => $progress,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($pending === null && $status->slug !== $task->status) {
            (new ApprovalService())->withdrawPending(
                'task',
                (int) $task->id,
                $organizationId,
                $actorId,
                'Status moved to ' . $status->name . ' before the waiting approval was decided.'
            );
        }

        ActivityLog::record($organizationId, $actorId, 'task.updated', 'Task "' . $data['title'] . '" was updated', 'task', (int) $task->id);
        Project::syncProgress($organizationId, (int) $project->id);
        if ((int) $project->id !== (int) $task->project_id) {
            Project::syncProgress($organizationId, (int) $task->project_id);
        }

        $assigneeChanged = $assigneeId !== null && (int) $assigneeId !== (int) ($task->assignee_user_id ?? 0);
        if ($assigneeChanged && $pending === null) {
            NotificationService::notifyUser(
                $organizationId,
                (int) $assigneeId,
                'Task assigned: ' . $data['title'],
                ($project->name ?: 'Project') . (!empty($data['due_date']) ? ' · Due ' . $data['due_date'] : ''),
                'tasks/' . (int) $task->id,
                $actorId
            );
        }

        if ($pending instanceof WorkflowPending) {
            throw $pending;
        }
    }

    public function changeStatus(Task $task, string $statusSlug, $progress, int $actorId): Task
    {
        if (!TaskAccess::canChangeStatus($task)) {
            throw new \RuntimeException('You cannot update this task.');
        }

        $status = $this->status((int) $task->organization_id, $statusSlug);
        $pending = $this->gateStatus($task, $status->slug, $actorId);
        if ($pending instanceof WorkflowPending) {
            throw $pending;
        }
        $nextProgress = TaskStatus::progressForSlug(
            TaskStatus::forOrganization((int) $task->organization_id),
            $status->slug
        );

        Task::updateById((int) $task->id, (int) $task->organization_id, [
            'status' => $status->slug,
            'progress' => $nextProgress,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($status->slug !== $task->status) {
            (new ApprovalService())->withdrawPending(
                'task',
                (int) $task->id,
                (int) $task->organization_id,
                $actorId,
                'Status moved to ' . $status->name . ' before the waiting approval was decided.'
            );
        }

        ActivityLog::record(
            (int) $task->organization_id,
            $actorId,
            'task.status_changed',
            'Task "' . $task->title . '" moved to ' . $status->name,
            'task',
            (int) $task->id
        );
        Project::syncProgress((int) $task->organization_id, (int) $task->project_id);

        $updated = Task::findInOrganization((int) $task->id, (int) $task->organization_id);
        if (!$updated instanceof Task) {
            throw new \RuntimeException('Task could not be updated.');
        }

        if ($updated->assignee_user_id !== null && (int) $updated->assignee_user_id > 0) {
            NotificationService::notifyUser(
                (int) $updated->organization_id,
                (int) $updated->assignee_user_id,
                'Task status: ' . $updated->title,
                'Now ' . ($updated->status_name ?: $status->name),
                'tasks/' . (int) $updated->id,
                $actorId
            );
        }
        if ($updated->reporter_user_id !== null
            && (int) $updated->reporter_user_id > 0
            && (int) $updated->reporter_user_id !== (int) ($updated->assignee_user_id ?? 0)
        ) {
            NotificationService::notifyUser(
                (int) $updated->organization_id,
                (int) $updated->reporter_user_id,
                'Task status: ' . $updated->title,
                'Now ' . ($updated->status_name ?: $status->name),
                'tasks/' . (int) $updated->id,
                $actorId
            );
        }

        return $updated;
    }

    private function gateStatus(Task $task, string $to, int $actorId): ?WorkflowPending
    {
        try {
            (new StatusWorkflowService())->gate(
                (int) $task->organization_id,
                'task',
                $task->status,
                $to,
                (int) $task->id,
                (int) $task->project_id,
                (int) $task->client_id,
                $actorId,
                $task->title
            );
        } catch (WorkflowPending $pending) {
            return $pending;
        }

        return null;
    }

    public function delete(Task $task, int $actorId): void
    {
        if (!TaskAccess::canManage($task)) {
            throw new \RuntimeException('You cannot remove this task.');
        }

        foreach (TaskAttachment::forTask((int) $task->id, (int) $task->organization_id) as $attachment) {
            FileUploader::deletePrivate($attachment->stored_path);
        }

        $title = $task->title;
        $id = (int) $task->id;
        $organizationId = (int) $task->organization_id;
        $projectId = (int) $task->project_id;
        Task::deleteInOrganization($id, $organizationId);
        Project::syncProgress($organizationId, $projectId);
        ActivityLog::record($organizationId, $actorId, 'task.deleted', 'Task "' . $title . '" was removed', 'task', $id);
    }

    public function addComment(Task $task, string $body, ?array $file, int $actorId): void
    {
        if (!TaskAccess::canComment($task)) {
            throw new \RuntimeException('You cannot comment on this task.');
        }

        $body = trim($body);
        if (strlen($body) > 4000) {
            throw new \RuntimeException('Comments must be 4000 characters or fewer.');
        }

        $stored = null;
        if ($file !== null) {
            if (!TaskAccess::canUpload($task)) {
                throw new \RuntimeException('You cannot attach files to this task.');
            }
            $stored = FileUploader::privateDocument($file, 'tasks/' . (int) $task->organization_id);
        }

        if ($body === '' && $stored === null) {
            throw new \RuntimeException('Write a comment or attach a file.');
        }

        if ($body === '') {
            $body = 'Attached a file.';
        }

        $now = date('Y-m-d H:i:s');
        $commentId = TaskComment::create([
            'organization_id' => $task->organization_id,
            'task_id' => $task->id,
            'user_id' => $actorId,
            'body' => $body,
            'created_at' => $now,
        ]);

        if ($stored !== null) {
            TaskAttachment::create([
                'organization_id' => $task->organization_id,
                'task_id' => $task->id,
                'comment_id' => $commentId,
                'user_id' => $actorId,
                'original_name' => $stored['original_name'],
                'stored_path' => $stored['stored_path'],
                'mime_type' => $stored['mime_type'],
                'size_bytes' => $stored['size_bytes'],
                'created_at' => $now,
            ]);
        }

        Task::updateById((int) $task->id, (int) $task->organization_id, ['updated_at' => $now]);
        ActivityLog::record((int) $task->organization_id, $actorId, 'task.commented', 'Comment added on "' . $task->title . '"', 'task', (int) $task->id);
    }

    public function deleteComment(Task $task, TaskComment $comment, int $actorId): void
    {
        if (!TaskAccess::canDeleteComment($task, $comment->user_id)) {
            throw new \RuntimeException('You cannot remove this comment.');
        }

        foreach (TaskAttachment::forTask((int) $task->id, (int) $task->organization_id) as $attachment) {
            if ((int) $attachment->comment_id === (int) $comment->id) {
                FileUploader::deletePrivate($attachment->stored_path);
            }
        }

        TaskComment::deleteInTask((int) $comment->id, (int) $task->id, (int) $task->organization_id);
        ActivityLog::record((int) $task->organization_id, $actorId, 'task.comment_deleted', 'Comment removed from "' . $task->title . '"', 'task', (int) $task->id);
    }

    public function addAttachment(Task $task, array $file, int $actorId): void
    {
        if (!TaskAccess::canUpload($task)) {
            throw new \RuntimeException('You cannot attach files to this task.');
        }

        $stored = FileUploader::privateDocument($file, 'tasks/' . (int) $task->organization_id);
        if ($stored === null) {
            throw new \RuntimeException('Choose a file to upload.');
        }

        $now = date('Y-m-d H:i:s');
        TaskAttachment::create([
            'organization_id' => $task->organization_id,
            'task_id' => $task->id,
            'comment_id' => null,
            'user_id' => $actorId,
            'original_name' => $stored['original_name'],
            'stored_path' => $stored['stored_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'created_at' => $now,
        ]);
        Task::updateById((int) $task->id, (int) $task->organization_id, ['updated_at' => $now]);
        ActivityLog::record((int) $task->organization_id, $actorId, 'task.attachment_added', 'File added to "' . $task->title . '"', 'task', (int) $task->id);
    }

    public function deleteAttachment(Task $task, TaskAttachment $attachment, int $actorId): void
    {
        if (!TaskAccess::canDeleteAttachment($task, $attachment->user_id)) {
            throw new \RuntimeException('You cannot remove this file.');
        }

        FileUploader::deletePrivate($attachment->stored_path);
        TaskAttachment::deleteInTask((int) $attachment->id, (int) $task->id, (int) $task->organization_id);
        ActivityLog::record((int) $task->organization_id, $actorId, 'task.attachment_deleted', 'File removed from "' . $task->title . '"', 'task', (int) $task->id);
    }

    private function project(int $organizationId, int $projectId): Project
    {
        $project = Project::findInOrganization($projectId, $organizationId);
        if (!$project instanceof Project) {
            throw new \RuntimeException('Choose a project in this organization.');
        }

        return $project;
    }

    private function status(int $organizationId, string $slug): TaskStatus
    {
        $status = TaskStatus::findBySlug($organizationId, $slug);
        if (!$status instanceof TaskStatus) {
            throw new \RuntimeException('Choose a valid status.');
        }

        return $status;
    }

    private function assignee(int $organizationId, $userId): ?int
    {
        if ($userId === null || $userId === '' || (int) $userId === 0) {
            return null;
        }

        $user = User::find((int) $userId);
        if (!$user instanceof User || (int) $user->organization_id !== $organizationId || $user->status !== 'active') {
            throw new \RuntimeException('Assignee must be an active person in this organization.');
        }

        $role = $user->role();
        if ($role !== null && $role->slug === 'client') {
            throw new \RuntimeException('Client accounts cannot be assigned tasks.');
        }

        return (int) $user->id;
    }

    private function assertDates($start, $due): void
    {
        $start = $this->nullableText($start);
        $due = $this->nullableText($due);
        if ($start !== null && $due !== null && $start > $due) {
            throw new \RuntimeException('The start date must be on or before the due date.');
        }
    }

    private function nullableText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
