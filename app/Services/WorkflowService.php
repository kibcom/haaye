<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkFlowRole;
use App\Models\WorkFlowTask;

final class WorkFlowService
{
    /** @var list<string> */
    public const STATUSES = ['todo', 'in_progress', 'review', 'done'];

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'todo' => 'To do',
            'in_progress' => 'In progress',
            'review' => 'Review',
            'done' => 'Done',
        ];
    }

    public function createRole(int $organizationId, array $input, int $actorId): WorkFlowRole
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || strlen($name) > 120) {
            throw new \RuntimeException('Enter a role name up to 120 characters.');
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (strlen($description) > 255) {
            throw new \RuntimeException('Description may not be longer than 255 characters.');
        }

        $sort = (int) ($input['sort_order'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $id = WorkFlowRole::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'sort_order' => $sort > 0 ? $sort : $this->nextRoleSort($organizationId),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'work_flow.role_created', 'Added workflow role "' . $name . '".', 'work_flow_role', $id);
        $role = WorkFlowRole::findInOrganization($id, $organizationId);
        if (!$role instanceof WorkFlowRole) {
            throw new \RuntimeException('The workflow role could not be saved.');
        }

        return $role;
    }

    public function updateRole(WorkFlowRole $role, array $input, int $actorId): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || strlen($name) > 120) {
            throw new \RuntimeException('Enter a role name up to 120 characters.');
        }
        $description = trim((string) ($input['description'] ?? ''));
        if (strlen($description) > 255) {
            throw new \RuntimeException('Description may not be longer than 255 characters.');
        }

        WorkFlowRole::updateById((int) $role->id, (int) $role->organization_id, [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'sort_order' => (int) ($input['sort_order'] ?? $role->sort_order),
            'is_active' => isset($input['is_active']) ? ((string) $input['is_active'] === '1' ? 1 : 0) : (int) $role->is_active,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record(
            (int) $role->organization_id,
            $actorId,
            'work_flow.role_updated',
            'Updated workflow role "' . $name . '".',
            'work_flow_role',
            (int) $role->id
        );
    }

    public function deleteRole(WorkFlowRole $role, int $actorId): void
    {
        WorkFlowRole::deleteInOrganization((int) $role->id, (int) $role->organization_id);
        ActivityLog::record(
            (int) $role->organization_id,
            $actorId,
            'work_flow.role_deleted',
            'Removed workflow role "' . $role->name . '".',
            'work_flow_role',
            (int) $role->id
        );
    }

    public function createTask(int $organizationId, array $input, int $actorId): WorkFlowTask
    {
        $payload = $this->taskPayload($organizationId, $input);
        $now = date('Y-m-d H:i:s');
        $id = WorkFlowTask::create([
            'organization_id' => $organizationId,
            'project_id' => $payload['project_id'],
            'client_id' => $payload['client_id'],
            'work_flow_role_id' => $payload['work_flow_role_id'],
            'title' => $payload['title'],
            'description' => $payload['description'],
            'assignee_user_id' => $payload['assignee_user_id'],
            'status' => $payload['status'],
            'due_date' => $payload['due_date'],
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'work_flow.task_created', 'Added workflow task "' . $payload['title'] . '".', 'task', $id);
        if ($payload['assignee_user_id']) {
            NotificationService::notifyUser(
                $organizationId,
                (int) $payload['assignee_user_id'],
                'Workflow task assigned',
                $payload['title'],
                'work-flow'
            );
        }

        $task = WorkFlowTask::findInOrganization($id, $organizationId);
        if (!$task instanceof WorkFlowTask) {
            throw new \RuntimeException('The workflow task could not be saved.');
        }

        return $task;
    }

    public function updateTask(WorkFlowTask $task, array $input, int $actorId): void
    {
        $payload = $this->taskPayload((int) $task->organization_id, $input);
        WorkFlowTask::updateById((int) $task->id, (int) $task->organization_id, [
            'project_id' => $payload['project_id'],
            'client_id' => $payload['client_id'],
            'work_flow_role_id' => $payload['work_flow_role_id'],
            'title' => $payload['title'],
            'description' => $payload['description'],
            'assignee_user_id' => $payload['assignee_user_id'],
            'status' => $payload['status'],
            'due_date' => $payload['due_date'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record(
            (int) $task->organization_id,
            $actorId,
            'work_flow.task_updated',
            'Updated workflow task "' . $payload['title'] . '".',
            'task',
            (int) $task->id
        );

        if ($payload['assignee_user_id'] && (int) $payload['assignee_user_id'] !== (int) $task->assignee_user_id) {
            NotificationService::notifyUser(
                (int) $task->organization_id,
                (int) $payload['assignee_user_id'],
                'Workflow task assigned',
                $payload['title'],
                'work-flow'
            );
        }
    }

    public function updateStatus(WorkFlowTask $task, string $status, int $actorId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('Choose a valid status.');
        }

        WorkFlowTask::updateById((int) $task->id, (int) $task->organization_id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record(
            (int) $task->organization_id,
            $actorId,
            'work_flow.task_status',
            'Moved workflow task "' . $task->title . '" to ' . (self::statusLabels()[$status] ?? $status) . '.',
            'task',
            (int) $task->id
        );
    }

    public function deleteTask(WorkFlowTask $task, int $actorId): void
    {
        WorkFlowTask::deleteInOrganization((int) $task->id, (int) $task->organization_id);
        ActivityLog::record(
            (int) $task->organization_id,
            $actorId,
            'work_flow.task_deleted',
            'Removed workflow task "' . $task->title . '".',
            'task',
            (int) $task->id
        );
    }

    public function canViewTask(WorkFlowTask $task): bool
    {
        $project = Project::findInOrganization((int) $task->project_id, (int) $task->organization_id);
        return $project instanceof Project && ProjectAccess::canView($project);
    }

    public function canManage(): bool
    {
        return Auth::can('work_flow.manage');
    }

    public function canAssign(): bool
    {
        return Auth::can('work_flow.assign') || Auth::can('work_flow.manage');
    }

    /** @return array<string, mixed> */
    private function taskPayload(int $organizationId, array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || strlen($title) > 190) {
            throw new \RuntimeException('Enter a task title up to 190 characters.');
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (strlen($description) > 4000) {
            throw new \RuntimeException('Description may not be longer than 4000 characters.');
        }

        $projectId = (int) ($input['project_id'] ?? 0);
        $project = Project::findInOrganization($projectId, $organizationId);
        if (!$project instanceof Project || !ProjectAccess::canView($project)) {
            throw new \RuntimeException('Choose a project you can access.');
        }

        $roleId = (int) ($input['work_flow_role_id'] ?? 0);
        $role = null;
        if ($roleId > 0) {
            $role = WorkFlowRole::findInOrganization($roleId, $organizationId);
            if (!$role instanceof WorkFlowRole || (int) $role->is_active !== 1) {
                throw new \RuntimeException('Choose an active workflow role.');
            }
        }

        $assigneeId = (int) ($input['assignee_user_id'] ?? 0);
        $assignee = null;
        if ($assigneeId > 0) {
            $assignee = User::findInOrganization($assigneeId, $organizationId);
            if (!$assignee instanceof User || $assignee->status !== 'active') {
                throw new \RuntimeException('Choose an active teammate to assign.');
            }
            if (($assignee->role_slug ?? '') === 'client') {
                throw new \RuntimeException('Client portal users cannot take workflow tasks.');
            }
        }

        $status = (string) ($input['status'] ?? 'todo');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'todo';
        }

        $due = trim((string) ($input['due_date'] ?? ''));
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            throw new \RuntimeException('Enter a valid due date.');
        }

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'project_id' => (int) $project->id,
            'client_id' => (int) $project->client_id,
            'work_flow_role_id' => $role?->id,
            'assignee_user_id' => $assignee?->id,
            'status' => $status,
            'due_date' => $due !== '' ? $due : null,
        ];
    }

    private function nextRoleSort(int $organizationId): int
    {
        $row = Database::query(
            'SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM work_flow_roles WHERE organization_id = ?',
            [$organizationId]
        )->fetch();

        return ((int) ($row['max_sort'] ?? 0)) + 1;
    }
}
