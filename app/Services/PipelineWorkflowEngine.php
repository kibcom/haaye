<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStage;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStage;

/**
 * Configurable multi-stage pipeline on projects (templates → runs → tasks).
 * Idempotent activation: never creates a second task for the same run stage.
 */
final class PipelineWorkflowEngine
{
    /**
     * @param array<int,int> $assigneesByTemplateStageId template_stage_id => user_id
     */
    public function startRun(
        int $organizationId,
        int $templateId,
        int $projectId,
        int $actorId,
        ?string $name = null,
        ?int $leadId = null,
        array $assigneesByTemplateStageId = [],
        ?int $approverUserId = null
    ): WorkflowRun {
        if (!\App\Core\Auth::can('pipeline.manage') && !\App\Core\Auth::can('pipeline.templates')) {
            throw new \RuntimeException('You cannot start a pipeline workflow.');
        }

        $template = WorkflowTemplate::findInOrganization($templateId, $organizationId);
        if (!$template instanceof WorkflowTemplate || !(int) $template->is_active) {
            throw new \RuntimeException('Choose an active workflow template.');
        }

        $project = Project::findInOrganization($projectId, $organizationId);
        if (!$project instanceof Project) {
            throw new \RuntimeException('Project not found.');
        }
        if (!ProjectAccess::canView($project)) {
            throw new \RuntimeException('You cannot start a workflow on this project.');
        }

        $stages = WorkflowTemplateStage::forTemplate($templateId, $organizationId, true);
        if ($stages === []) {
            throw new \RuntimeException('This template has no active stages.');
        }

        $ceoApprover = $approverUserId ?: $actorId;
        $missing = [];
        foreach ($stages as $stage) {
            $picked = (int) ($assigneesByTemplateStageId[(int) $stage->id] ?? 0);
            $fallback = (int) ($stage->default_assignee_user_id ?? 0);
            if ($picked < 1 && $fallback < 1) {
                $missing[] = $stage->name;
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException('Assign a person to every stage before starting: ' . implode(', ', $missing) . '.');
        }

        $now = date('Y-m-d H:i:s');
        $runId = WorkflowRun::create([
            'organization_id' => $organizationId,
            'template_id' => $templateId,
            'project_id' => $projectId,
            'client_id' => (int) $project->client_id,
            'lead_id' => $leadId,
            'name' => $name !== null && $name !== '' ? $name : ($template->name . ' — ' . $project->name),
            'status' => 'active',
            // CEO flow: auto-create next task after approval when assignees are already set.
            'progression_mode' => 'automatic',
            'started_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($stages as $stage) {
            $assignee = (int) ($assigneesByTemplateStageId[(int) $stage->id] ?? 0);
            if ($assignee < 1) {
                $assignee = (int) ($stage->default_assignee_user_id ?? 0);
            }
            WorkflowRunStage::create([
                'organization_id' => $organizationId,
                'run_id' => $runId,
                'template_stage_id' => (int) $stage->id,
                'name' => $stage->name,
                'sort_order' => (int) $stage->sort_order,
                'status' => 'pending',
                'work_flow_role_id' => $stage->work_flow_role_id,
                'assignee_user_id' => $assignee > 0 ? $assignee : null,
                'requires_approval' => 1,
                'approver_user_id' => $stage->approver_user_id ?: $ceoApprover,
                'auto_advance' => 1,
                'instructions' => $stage->instructions ?: $stage->description,
                'deliverables_json' => $stage->deliverables_json,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $run = WorkflowRun::findInOrganization($runId, $organizationId);
        if (!$run instanceof WorkflowRun) {
            throw new \RuntimeException('Workflow run could not be created.');
        }

        ActivityLog::record(
            $organizationId,
            $actorId,
            'pipeline.started',
            'Started workflow "' . $run->name . '" on ' . $project->name . '.',
            'workflow_run',
            $runId
        );

        $this->activateNextPending($run, $actorId, true);

        $fresh = WorkflowRun::findInOrganization($runId, $organizationId);
        return $fresh instanceof WorkflowRun ? $fresh : $run;
    }

    public function onStageApproved(WorkflowRunStage $stage, int $actorId): void
    {
        $run = WorkflowRun::findInOrganization((int) $stage->run_id, (int) $stage->organization_id);
        if (!$run instanceof WorkflowRun || $run->status !== 'active') {
            return;
        }

        $mode = $run->progression_mode;
        $shouldAuto = $mode === 'automatic'
            || ($mode === 'hybrid' && (int) $stage->auto_advance === 1);

        if ($shouldAuto) {
            try {
                $this->activateNextPending($run, $actorId, true);
            } catch (\RuntimeException $e) {
                NotificationService::notifyUser(
                    (int) $run->organization_id,
                    $actorId,
                    'Assign next stage: ' . $run->name,
                    $e->getMessage(),
                    'pipeline/runs/' . (int) $run->id
                );
            }
            return;
        }

        // Manual / hybrid pause: notify managers; do not spawn next task yet.
        WorkflowRun::updateById((int) $run->id, (int) $run->organization_id, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        NotificationService::notifyUser(
            (int) $run->organization_id,
            $actorId,
            'Workflow paused: ' . $run->name,
            'Stage "' . $stage->name . '" is approved. Confirm the next stage when ready.',
            'pipeline/runs/' . (int) $run->id
        );
        $project = Project::findInOrganization((int) $run->project_id, (int) $run->organization_id);
        if ($project instanceof Project && $project->manager_user_id && (int) $project->manager_user_id !== $actorId) {
            NotificationService::notifyUser(
                (int) $run->organization_id,
                (int) $project->manager_user_id,
                'Confirm next stage: ' . $run->name,
                '"' . $stage->name . '" was approved. Next stage awaits manual confirmation.',
                'pipeline/runs/' . (int) $run->id,
                $actorId
            );
        }
    }

    public function confirmNext(WorkflowRun $run, int $actorId, ?int $assigneeUserId = null): ?WorkflowRunStage
    {
        if (!\App\Core\Auth::can('pipeline.manage') && !\App\Core\Auth::can('pipeline.override')) {
            throw new \RuntimeException('You cannot advance this workflow.');
        }
        if ($run->status !== 'active' && $run->status !== 'paused') {
            throw new \RuntimeException('This workflow is not active.');
        }
        if ($run->status === 'paused') {
            WorkflowRun::updateById((int) $run->id, (int) $run->organization_id, [
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $run = WorkflowRun::findInOrganization((int) $run->id, (int) $run->organization_id) ?? $run;
        }

        if ($assigneeUserId !== null && $assigneeUserId > 0) {
            foreach (WorkflowRunStage::forRun((int) $run->id, (int) $run->organization_id) as $stage) {
                if ($stage->status === 'pending') {
                    WorkflowRunStage::updateById((int) $stage->id, (int) $run->organization_id, [
                        'assignee_user_id' => $assigneeUserId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    break;
                }
            }
        }

        return $this->activateNextPending($run, $actorId, true);
    }

    public function pause(WorkflowRun $run, int $actorId): void
    {
        $this->assertManage();
        WorkflowRun::updateById((int) $run->id, (int) $run->organization_id, [
            'status' => 'paused',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $run->organization_id, $actorId, 'pipeline.paused', 'Paused workflow "' . $run->name . '".', 'workflow_run', (int) $run->id);
    }

    public function resume(WorkflowRun $run, int $actorId): void
    {
        $this->assertManage();
        WorkflowRun::updateById((int) $run->id, (int) $run->organization_id, [
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $run->organization_id, $actorId, 'pipeline.resumed', 'Resumed workflow "' . $run->name . '".', 'workflow_run', (int) $run->id);
    }

    public function cancel(WorkflowRun $run, int $actorId): void
    {
        $this->assertManage();
        $now = date('Y-m-d H:i:s');
        WorkflowRun::updateById((int) $run->id, (int) $run->organization_id, [
            'status' => 'cancelled',
            'updated_at' => $now,
            'completed_at' => $now,
        ]);
        foreach (WorkflowRunStage::forRun((int) $run->id, (int) $run->organization_id) as $stage) {
            if (in_array($stage->status, ['approved', 'skipped', 'cancelled'], true)) {
                continue;
            }
            WorkflowRunStage::updateById((int) $stage->id, (int) $run->organization_id, [
                'status' => 'cancelled',
                'updated_at' => $now,
            ]);
        }
        ActivityLog::record((int) $run->organization_id, $actorId, 'pipeline.cancelled', 'Cancelled workflow "' . $run->name . '".', 'workflow_run', (int) $run->id);
    }

    public function reassignStage(WorkflowRunStage $stage, ?int $assigneeUserId, int $actorId): void
    {
        if (!\App\Core\Auth::can('pipeline.manage') && !\App\Core\Auth::can('tasks.assign')) {
            throw new \RuntimeException('You cannot reassign this stage.');
        }
        if ($assigneeUserId === null || $assigneeUserId < 1) {
            throw new \RuntimeException('Pick a person to assign this stage.');
        }
        $now = date('Y-m-d H:i:s');
        WorkflowRunStage::updateById((int) $stage->id, (int) $stage->organization_id, [
            'assignee_user_id' => $assigneeUserId,
            'updated_at' => $now,
        ]);
        if ($stage->task_id) {
            Task::updateById((int) $stage->task_id, (int) $stage->organization_id, [
                'assignee_user_id' => $assigneeUserId,
                'updated_at' => $now,
            ]);
        }
        ActivityLog::record(
            (int) $stage->organization_id,
            $actorId,
            'pipeline.reassigned',
            'Reassigned stage "' . $stage->name . '".',
            'workflow_run_stage',
            (int) $stage->id
        );
        if ($assigneeUserId) {
            NotificationService::notifyUser(
                (int) $stage->organization_id,
                $assigneeUserId,
                'Stage assigned: ' . $stage->name,
                'You have been assigned a workflow stage.',
                $stage->task_id ? ('tasks/' . (int) $stage->task_id) : ('pipeline/runs/' . (int) $stage->run_id),
                $actorId
            );
        }
    }

    private function activateNextPending(WorkflowRun $run, int $actorId, bool $createTask): ?WorkflowRunStage
    {
        $orgId = (int) $run->organization_id;
        $stages = WorkflowRunStage::forRun((int) $run->id, $orgId);

        // Complete run when every stage is terminal.
        $pending = array_filter(
            $stages,
            static fn (WorkflowRunStage $s): bool => !in_array($s->status, ['approved', 'skipped', 'cancelled'], true)
        );
        if ($pending === []) {
            $now = date('Y-m-d H:i:s');
            WorkflowRun::updateById((int) $run->id, $orgId, [
                'status' => 'completed',
                'current_stage_id' => null,
                'updated_at' => $now,
                'completed_at' => $now,
            ]);
            ActivityLog::record($orgId, $actorId, 'pipeline.completed', 'Completed workflow "' . $run->name . '".', 'workflow_run', (int) $run->id);
            return null;
        }

        // Prefer an already-active (non-approved) stage; otherwise first pending.
        $active = null;
        foreach ($stages as $stage) {
            if (in_array($stage->status, ['ready', 'in_progress', 'submitted', 'changes_requested'], true)) {
                $active = $stage;
                break;
            }
        }
        if ($active instanceof WorkflowRunStage) {
            return $active;
        }

        $next = null;
        foreach ($stages as $stage) {
            if ($stage->status === 'pending') {
                $next = $stage;
                break;
            }
        }
        if (!$next instanceof WorkflowRunStage) {
            return null;
        }

        if (!$next->assignee_user_id || (int) $next->assignee_user_id < 1) {
            throw new \RuntimeException(
                'Assign a person to "' . $next->name . '" before creating the task.'
            );
        }

        // Idempotent: if task already linked, just mark ready.
        $now = date('Y-m-d H:i:s');
        $taskId = $next->task_id;
        if ($createTask && ($taskId === null || (int) $taskId < 1)) {
            // Guard against double-create under concurrent requests.
            Database::connection()->beginTransaction();
            try {
                $locked = WorkflowRunStage::findInOrganization((int) $next->id, $orgId);
                if ($locked instanceof WorkflowRunStage && $locked->task_id) {
                    $taskId = (int) $locked->task_id;
                } else {
                    TaskStatus::ensureLifecycleStatuses($orgId);
                    $project = Project::findInOrganization((int) $run->project_id, $orgId);
                    $managerId = $project instanceof Project ? $project->manager_user_id : null;
                    $taskId = Task::create([
                        'organization_id' => $orgId,
                        'project_id' => (int) $run->project_id,
                        'client_id' => (int) $run->client_id,
                        'lead_id' => $run->lead_id,
                        'title' => $next->name,
                        'description' => $next->instructions,
                        'completion_criteria' => $next->deliverables_json,
                        'assignee_user_id' => (int) $next->assignee_user_id,
                        'reporter_user_id' => $actorId,
                        'manager_user_id' => $next->approver_user_id ?: $managerId ?: $actorId,
                        'work_flow_role_id' => $next->work_flow_role_id,
                        'workflow_run_id' => (int) $run->id,
                        'workflow_run_stage_id' => (int) $next->id,
                        'priority' => 'medium',
                        'status' => TaskLifecycle::ASSIGNED,
                        'progress' => TaskStatus::progressForSlug(TaskStatus::forOrganization($orgId), TaskLifecycle::ASSIGNED),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                Database::connection()->commit();
            } catch (\Throwable $e) {
                Database::connection()->rollBack();
                throw $e;
            }
        }

        WorkflowRunStage::updateById((int) $next->id, $orgId, [
            'status' => 'ready',
            'task_id' => $taskId,
            'activated_at' => $now,
            'updated_at' => $now,
        ]);
        WorkflowRun::updateById((int) $run->id, $orgId, [
            'current_stage_id' => (int) $next->id,
            'updated_at' => $now,
        ]);

        if ($taskId && $next->assignee_user_id) {
            NotificationService::notifyUser(
                $orgId,
                (int) $next->assignee_user_id,
                'New stage task: ' . $next->name,
                'A workflow stage is ready for you.',
                'tasks/' . (int) $taskId,
                $actorId
            );
        }

        ActivityLog::record(
            $orgId,
            $actorId,
            'pipeline.stage_activated',
            'Activated stage "' . $next->name . '" on "' . $run->name . '".',
            'workflow_run',
            (int) $run->id
        );

        return WorkflowRunStage::findInOrganization((int) $next->id, $orgId);
    }

    private function assertManage(): void
    {
        if (!\App\Core\Auth::can('pipeline.manage') && !\App\Core\Auth::can('pipeline.override')) {
            throw new \RuntimeException('You cannot manage this workflow.');
        }
    }
}
