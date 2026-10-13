<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Approval;
use App\Models\DeliverableStatus;
use App\Models\ProjectStatus;
use App\Models\TaskStatus;
use App\Models\WorkflowTransition;

/**
 * Phase 5 status-transition workflows (separate from CRM WorkFlowService).
 */
final class StatusWorkflowService
{
    /** @var array<string, string> */
    public const TYPES = [
        'task' => 'Tasks',
        'project' => 'Projects',
        'deliverable' => 'Deliverables',
    ];

    public function gate(
        int $organizationId,
        string $entityType,
        string $fromStatus,
        string $toStatus,
        int $entityId,
        int $projectId,
        int $clientId,
        int $actorId,
        string $label
    ): void {
        if ($fromStatus === $toStatus) {
            return;
        }

        if (!WorkflowTransition::hasAny($organizationId, $entityType)) {
            return;
        }

        $transition = WorkflowTransition::match($organizationId, $entityType, $fromStatus, $toStatus);
        if (!$transition instanceof WorkflowTransition) {
            throw new \RuntimeException('That status change is not allowed by the workflow.');
        }

        $kind = (string) $transition->requires_approval;
        if ($kind === '' || $kind === 'none') {
            return;
        }

        $existing = Approval::pendingKind($organizationId, $entityType, $entityId, $kind);
        if ($existing instanceof Approval) {
            throw new WorkflowPending($existing);
        }

        $id = Approval::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId > 0 ? $projectId : null,
            'client_id' => $clientId > 0 ? $clientId : null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $label,
            'approval_kind' => $kind,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'requested_by' => $actorId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $approval = Approval::findInOrganization($id, $organizationId);
        if (!$approval instanceof Approval) {
            throw new \RuntimeException('Approval request could not be created.');
        }

        throw new WorkflowPending($approval);
    }

    /**
     * @param list<\App\Models\TaskStatus> $statuses
     * @return array{0:?\App\Models\TaskStatus,1:?\App\Models\TaskStatus}
     */
    public static function taskNeighbors(int $organizationId, string $currentStatus, array $statuses): array
    {
        $bySlug = [];
        foreach ($statuses as $status) {
            $bySlug[(string) $status->slug] = $status;
        }

        $next = null;
        $prev = null;
        if (!WorkflowTransition::hasAny($organizationId, 'task')) {
            $ordered = array_values($statuses);
            foreach ($ordered as $index => $status) {
                if ((string) $status->slug !== $currentStatus) {
                    continue;
                }
                $next = $ordered[$index + 1] ?? null;
                $prev = $index > 0 ? $ordered[$index - 1] : null;
                break;
            }

            return [$next, $prev];
        }

        foreach (WorkflowTransition::forOrganization($organizationId, 'task') as $transition) {
            if ($transition->from_status === $currentStatus && isset($bySlug[$transition->to_status]) && $next === null) {
                $next = $bySlug[$transition->to_status];
            }
            if ($transition->to_status === $currentStatus && isset($bySlug[$transition->from_status]) && $prev === null) {
                $prev = $bySlug[$transition->from_status];
            }
        }

        return [$next, $prev];
    }

    /**
     * @return list<array{slug:string,name:string}>
     */
    public function statusesForType(int $organizationId, string $type): array
    {
        return match ($type) {
            'task' => array_map(
                static fn (TaskStatus $s): array => ['slug' => $s->slug, 'name' => $s->name],
                TaskStatus::forOrganization($organizationId)
            ),
            'project' => array_map(
                static fn (ProjectStatus $s): array => ['slug' => $s->slug, 'name' => $s->name],
                ProjectStatus::forOrganization($organizationId)
            ),
            'deliverable' => array_map(
                static fn (DeliverableStatus $s): array => ['slug' => $s->slug, 'name' => $s->name],
                DeliverableStatus::forOrganization($organizationId)
            ),
            default => [],
        };
    }
}
