<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Deliverable;
use App\Models\DeliverableStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;

final class ApprovalService
{
    public static function viewerClientId(): ?int
    {
        $role = Auth::role();
        if ($role === null || $role->slug !== 'client') {
            return null;
        }

        $user = Auth::user();
        if (!$user instanceof User || $user->client_id === null || (int) $user->client_id <= 0) {
            return -1;
        }

        return (int) $user->client_id;
    }

    public static function projectRestriction(): ?int
    {
        if (self::viewerClientId() !== null || ProjectAccess::seesAll()) {
            return null;
        }

        return Auth::id();
    }

    public static function canSee(Approval $approval): bool
    {
        if ((int) $approval->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        $clientId = self::viewerClientId();
        if ($clientId !== null) {
            return $approval->approval_kind === 'client' && (int) $approval->client_id === $clientId;
        }

        if (!Auth::can('approvals.view') && !Auth::can('approvals.decide')) {
            return false;
        }

        if (ProjectAccess::seesAll()) {
            return true;
        }

        $userId = Auth::id();
        if ($userId !== null && (int) $approval->requested_by === $userId) {
            return true;
        }

        if ($approval->project_id === null || $userId === null) {
            return false;
        }

        $project = Project::findInOrganization((int) $approval->project_id, (int) $approval->organization_id);
        return $project instanceof Project && ProjectAccess::canView($project);
    }

    public static function canDecide(Approval $approval): bool
    {
        if (!self::canSee($approval) || $approval->status !== 'pending') {
            return false;
        }

        if ($approval->approval_kind === 'client') {
            $clientId = self::viewerClientId();
            return $clientId !== null && $clientId > 0 && (int) $approval->client_id === $clientId && Auth::can('approvals.client');
        }

        if (Auth::can('approvals.decide')) {
            return true;
        }

        // Task submission approvals can also be decided with tasks.approve.
        return $approval->entity_type === 'task' && Auth::can('tasks.approve');
    }

    public function decide(Approval $approval, string $decision, string $comment, int $actorId): void
    {
        if (!self::canDecide($approval)) {
            throw new \RuntimeException('You cannot decide this approval.');
        }

        if (!in_array($decision, ['approved', 'rejected', 'changes_requested'], true)) {
            throw new \RuntimeException('Choose a decision.');
        }

        $comment = trim($comment);
        if ($decision !== 'approved' && $comment === '') {
            throw new \RuntimeException('Add a comment with this decision.');
        }
        if (strlen($comment) > 2000) {
            throw new \RuntimeException('The comment must be 2000 characters or fewer.');
        }

        $now = date('Y-m-d H:i:s');
        Approval::decide((int) $approval->id, (int) $approval->organization_id, [
            'status' => $decision,
            'decided_by' => $actorId,
            'decision_comment' => $comment !== '' ? $comment : null,
            'decided_at' => $now,
        ]);

        if ($decision === 'approved') {
            $this->apply($approval);
        } elseif ($approval->entity_type === 'task' && in_array($decision, ['changes_requested', 'rejected'], true)) {
            $task = Task::findInOrganization((int) $approval->entity_id, (int) $approval->organization_id);
            if ($task instanceof Task) {
                (new TaskLifecycleService())->applyDecision(
                    $task,
                    $decision === 'rejected' ? 'changes_requested' : $decision,
                    $actorId,
                    $comment
                );
            }
        } elseif ($approval->entity_type === 'deliverable') {
            $column = $approval->approval_kind === 'client' ? 'client_approval_status' : 'internal_approval_status';
            Deliverable::updateById((int) $approval->entity_id, (int) $approval->organization_id, [
                $column => $decision,
                'updated_at' => $now,
            ]);
            if ($decision === 'changes_requested' && $approval->approval_kind === 'client') {
                $status = DeliverableStatus::findBySlug((int) $approval->organization_id, 'changes_requested');
                if ($status instanceof DeliverableStatus) {
                    Deliverable::updateById((int) $approval->entity_id, (int) $approval->organization_id, [
                        'status' => 'changes_requested',
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $verb = $decision === 'changes_requested' ? 'requested changes on' : $decision;
        ActivityLog::record(
            (int) $approval->organization_id,
            $actorId,
            'approval.' . $decision,
            ucfirst($verb) . ' "' . $approval->entity_label . '"',
            $approval->entity_type,
            (int) $approval->entity_id
        );

        if ($approval->requested_by !== null && (int) $approval->requested_by > 0) {
            $decisionLabel = $decision === 'changes_requested' ? 'Changes requested' : ucfirst($decision);
            NotificationService::notifyUser(
                (int) $approval->organization_id,
                (int) $approval->requested_by,
                $decisionLabel . ': ' . $approval->entity_label,
                $comment !== '' ? $comment : 'A decision was made on your approval request.',
                'approvals/' . (int) $approval->id,
                $actorId
            );
        }
    }

    /**
     * Drop waiting approvals when the record moves to another status first.
     * Keeps an activity log line for each cancelled request.
     */
    public function withdrawPending(string $entityType, int $entityId, int $organizationId, int $actorId, string $reason): void
    {
        $cancelled = Approval::cancelPendingForEntity(
            $organizationId,
            $entityType,
            $entityId,
            $actorId,
            $reason
        );

        foreach ($cancelled as $approval) {
            if ($approval->entity_type === 'deliverable') {
                $column = $approval->approval_kind === 'client' ? 'client_approval_status' : 'internal_approval_status';
                Deliverable::updateById((int) $approval->entity_id, $organizationId, [
                    $column => 'none',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            ActivityLog::record(
                $organizationId,
                $actorId,
                'approval.cancelled',
                'Withdrew approval for "' . $approval->entity_label . '" (' . $approval->from_status . ' → ' . $approval->to_status . '). ' . $reason,
                $entityType,
                $entityId
            );

            if ($approval->requested_by !== null && (int) $approval->requested_by > 0) {
                NotificationService::notifyUser(
                    $organizationId,
                    (int) $approval->requested_by,
                    'Approval withdrawn: ' . $approval->entity_label,
                    $reason,
                    'approvals/' . (int) $approval->id,
                    $actorId
                );
            }
            if ($approval->approval_kind === 'client' && $approval->client_id !== null) {
                NotificationService::notifyClient(
                    $organizationId,
                    (int) $approval->client_id,
                    'Approval withdrawn: ' . $approval->entity_label,
                    $reason,
                    'portal/notifications'
                );
            }
        }
    }

    private function apply(Approval $approval): void
    {
        $now = date('Y-m-d H:i:s');
        $orgId = (int) $approval->organization_id;
        $entityId = (int) $approval->entity_id;

        if ($approval->entity_type === 'task') {
            $task = Task::findInOrganization($entityId, $orgId);
            if ($task instanceof Task && TaskLifecycle::canApprove((string) $task->status)) {
                (new TaskLifecycleService())->applyDecision($task, 'approved', (int) (\App\Core\Auth::id() ?? 0));
                return;
            }
            $payload = [
                'status' => $approval->to_status,
                'progress' => TaskStatus::progressForSlug(
                    TaskStatus::forOrganization($orgId),
                    (string) $approval->to_status
                ),
                'updated_at' => $now,
            ];
            Task::updateById($entityId, $orgId, $payload);
            $task = Task::findInOrganization($entityId, $orgId);
            if ($task instanceof Task) {
                Project::syncProgress($orgId, (int) $task->project_id);
                if ($task->assignee_user_id !== null && (int) $task->assignee_user_id > 0) {
                    NotificationService::notifyUser(
                        $orgId,
                        (int) $task->assignee_user_id,
                        'Task approved: ' . $task->title,
                        'Status is now ' . ($task->status_name ?: $approval->to_status),
                        'tasks/' . (int) $task->id
                    );
                }
            }
            return;
        }

        if ($approval->entity_type === 'project') {
            Project::updateById($entityId, $orgId, [
                'status' => $approval->to_status,
                'updated_at' => $now,
            ]);
            return;
        }

        if ($approval->entity_type === 'milestone') {
            $payload = ['status' => $approval->to_status, 'updated_at' => $now];
            if ($approval->to_status === 'completed') {
                $payload['progress'] = 100;
                $payload['completed_at'] = $now;
            }
            Milestone::updateById($entityId, $orgId, $payload);
            return;
        }

        if ($approval->entity_type === 'deliverable') {
            $column = $approval->approval_kind === 'client' ? 'client_approval_status' : 'internal_approval_status';
            Deliverable::updateById($entityId, $orgId, [
                'status' => $approval->to_status,
                $column => 'approved',
                'updated_at' => $now,
            ]);
        }
    }
}
