<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\WorkflowRunStage;

final class TaskLifecycleService
{
    public function start(Task $task, int $actorId): Task
    {
        if (!TaskAccess::canChangeStatus($task)) {
            throw new \RuntimeException('You cannot start this task.');
        }
        if (!TaskLifecycle::canStart((string) $task->status)) {
            throw new \RuntimeException('This task cannot be started from its current status.');
        }

        return $this->setStatus($task, TaskLifecycle::IN_PROGRESS, $actorId, 'Started work on task.');
    }

    public function submit(Task $task, int $actorId, string $note = ''): Approval
    {
        if (!Auth::can('tasks.submit') && !TaskAccess::canChangeStatus($task)) {
            throw new \RuntimeException('You cannot submit this task.');
        }
        $userId = Auth::id();
        if ($userId === null || ((int) $task->assignee_user_id !== $userId && !Auth::can('tasks.update'))) {
            throw new \RuntimeException('Only the assignee (or a manager) can submit this task.');
        }
        if (!TaskLifecycle::canSubmit((string) $task->status)) {
            throw new \RuntimeException('This task is not ready to submit.');
        }

        // Employees cannot approve their own submission via this path.
        $toStatus = TaskLifecycle::nextAfterSubmit((string) $task->status);
        $now = date('Y-m-d H:i:s');

        $existing = Approval::pendingKind((int) $task->organization_id, 'task', (int) $task->id, 'internal');
        if ($existing instanceof Approval) {
            throw new \RuntimeException('A submission is already waiting for approval.');
        }

        TaskStatus::ensureLifecycleStatuses((int) $task->organization_id);
        Task::updateById((int) $task->id, (int) $task->organization_id, [
            'status' => $toStatus,
            'progress' => TaskStatus::progressForSlug(TaskStatus::forOrganization((int) $task->organization_id), $toStatus),
            'submitted_at' => $now,
            'submission_note' => $note !== '' ? $note : null,
            'updated_at' => $now,
        ]);

        $stage = WorkflowRunStage::findByTask((int) $task->id, (int) $task->organization_id);
        if ($stage instanceof WorkflowRunStage) {
            WorkflowRunStage::updateById((int) $stage->id, (int) $task->organization_id, [
                'status' => 'submitted',
                'submitted_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $approvalId = Approval::create([
            'organization_id' => (int) $task->organization_id,
            'project_id' => (int) $task->project_id,
            'client_id' => (int) $task->client_id,
            'entity_type' => 'task',
            'entity_id' => (int) $task->id,
            'entity_label' => $task->title,
            'approval_kind' => 'internal',
            'from_status' => $toStatus,
            'to_status' => TaskLifecycle::APPROVED,
            'requested_by' => $actorId,
            'created_at' => $now,
        ]);

        ActivityLog::record(
            (int) $task->organization_id,
            $actorId,
            'task.submitted',
            'Submitted "' . $task->title . '" for approval.',
            'task',
            (int) $task->id
        );

        $this->notifyApprovers($task, $actorId, $approvalId);

        $approval = Approval::findInOrganization($approvalId, (int) $task->organization_id);
        if (!$approval instanceof Approval) {
            throw new \RuntimeException('Submission could not be recorded.');
        }

        Project::syncProgress((int) $task->organization_id, (int) $task->project_id);

        return $approval;
    }

    public function applyDecision(Task $task, string $decision, int $actorId, string $comment = ''): void
    {
        $now = date('Y-m-d H:i:s');
        $orgId = (int) $task->organization_id;

        if ($decision === 'approved') {
            if ((int) $task->assignee_user_id === $actorId && !Auth::can('pipeline.override')) {
                throw new \RuntimeException('You cannot approve your own submission.');
            }
            Task::updateById((int) $task->id, $orgId, [
                'status' => TaskLifecycle::APPROVED,
                'progress' => 100,
                'approved_at' => $now,
                'approved_by' => $actorId,
                'updated_at' => $now,
            ]);
            $stage = WorkflowRunStage::findByTask((int) $task->id, $orgId);
            if ($stage instanceof WorkflowRunStage) {
                WorkflowRunStage::updateById((int) $stage->id, $orgId, [
                    'status' => 'approved',
                    'approved_at' => $now,
                    'updated_at' => $now,
                ]);
                (new PipelineWorkflowEngine())->onStageApproved($stage, $actorId);
            }
            ActivityLog::record($orgId, $actorId, 'task.approved', 'Approved "' . $task->title . '".', 'task', (int) $task->id);
            Project::syncProgress($orgId, (int) $task->project_id);
            return;
        }

        if ($decision === 'changes_requested') {
            Task::updateById((int) $task->id, $orgId, [
                'status' => TaskLifecycle::CHANGES_REQUESTED,
                'progress' => TaskStatus::progressForSlug(TaskStatus::forOrganization($orgId), TaskLifecycle::CHANGES_REQUESTED),
                'updated_at' => $now,
            ]);
            if ($comment !== '') {
                \App\Models\TaskComment::create([
                    'organization_id' => $orgId,
                    'task_id' => (int) $task->id,
                    'user_id' => $actorId,
                    'body' => 'Changes requested: ' . $comment,
                    'created_at' => $now,
                ]);
            }
            $stage = WorkflowRunStage::findByTask((int) $task->id, $orgId);
            if ($stage instanceof WorkflowRunStage) {
                WorkflowRunStage::updateById((int) $stage->id, $orgId, [
                    'status' => 'changes_requested',
                    'updated_at' => $now,
                ]);
            }
            ActivityLog::record($orgId, $actorId, 'task.changes_requested', 'Requested changes on "' . $task->title . '".', 'task', (int) $task->id);
            if ($task->assignee_user_id) {
                NotificationService::notifyUser(
                    $orgId,
                    (int) $task->assignee_user_id,
                    'Changes requested: ' . $task->title,
                    $comment !== '' ? $comment : 'Please revise and resubmit.',
                    'tasks/' . (int) $task->id,
                    $actorId
                );
            }
            Project::syncProgress($orgId, (int) $task->project_id);
        }
    }

    private function setStatus(Task $task, string $status, int $actorId, string $message): Task
    {
        $now = date('Y-m-d H:i:s');
        TaskStatus::ensureLifecycleStatuses((int) $task->organization_id);
        Task::updateById((int) $task->id, (int) $task->organization_id, [
            'status' => $status,
            'progress' => TaskStatus::progressForSlug(TaskStatus::forOrganization((int) $task->organization_id), $status),
            'updated_at' => $now,
        ]);
        ActivityLog::record((int) $task->organization_id, $actorId, 'task.status', $message, 'task', (int) $task->id);
        Project::syncProgress((int) $task->organization_id, (int) $task->project_id);
        $updated = Task::findInOrganization((int) $task->id, (int) $task->organization_id);
        if (!$updated instanceof Task) {
            throw new \RuntimeException('Task could not be updated.');
        }

        return $updated;
    }

    private function notifyApprovers(Task $task, int $actorId, int $approvalId): void
    {
        $orgId = (int) $task->organization_id;
        $notified = [];
        $notify = static function (int $userId) use ($orgId, $task, $approvalId, $actorId, &$notified): void {
            if ($userId < 1 || isset($notified[$userId]) || $userId === $actorId) {
                return;
            }
            $notified[$userId] = true;
            NotificationService::notifyUser(
                $orgId,
                $userId,
                'Approve: ' . $task->title,
                'Work was submitted. One click to approve or request changes.',
                'approvals/' . $approvalId,
                $actorId
            );
        };

        $stage = WorkflowRunStage::findByTask((int) $task->id, $orgId);
        if ($stage instanceof WorkflowRunStage && $stage->approver_user_id) {
            $notify((int) $stage->approver_user_id);
        }
        if (!empty($task->manager_user_id)) {
            $notify((int) $task->manager_user_id);
        }
        $project = Project::findInOrganization((int) $task->project_id, $orgId);
        if ($project instanceof Project && $project->manager_user_id) {
            $notify((int) $project->manager_user_id);
        }

        // Always ping CEO / owners / GMs who can approve tasks.
        $ceoRows = \App\Core\Database::query(
            "SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.organization_id = ? AND u.status = 'active'
               AND r.slug IN ('owner', 'admin', 'general_manager')",
            [$orgId]
        )->fetchAll();
        foreach ($ceoRows as $row) {
            $notify((int) $row['id']);
        }
    }
}
