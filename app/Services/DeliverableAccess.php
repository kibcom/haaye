<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Deliverable;
use App\Models\Project;
use App\Models\User;

final class DeliverableAccess
{
    public static function clientScope(): ?int
    {
        return ApprovalService::viewerClientId();
    }

    public static function visibleStatuses(): ?array
    {
        if (self::clientScope() === null) {
            return null;
        }

        return ['client_review', 'changes_requested', 'approved', 'delivered'];
    }

    public static function restrictToUserId(): ?int
    {
        if (self::clientScope() !== null || ProjectAccess::seesAll()) {
            return null;
        }

        return Auth::id();
    }

    public static function canView(Deliverable $deliverable): bool
    {
        if ((int) $deliverable->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        $clientId = self::clientScope();
        if ($clientId !== null) {
            if (!Auth::can('deliverables.view') || (int) $deliverable->client_id !== $clientId) {
                return false;
            }

            $allowed = self::visibleStatuses() ?? [];
            return in_array($deliverable->status, $allowed, true);
        }

        if (!Auth::can('deliverables.view')) {
            return false;
        }

        if (ProjectAccess::seesAll()) {
            return true;
        }

        $userId = Auth::id();
        if ($userId !== null && (int) $deliverable->assignee_user_id === $userId) {
            return true;
        }

        $project = Project::findInOrganization((int) $deliverable->project_id, (int) $deliverable->organization_id);
        return $project instanceof Project && ProjectAccess::canView($project);
    }

    public static function canCreate(Project $project): bool
    {
        return Auth::can('deliverables.create') && ProjectAccess::canView($project) && self::clientScope() === null;
    }

    public static function canUpdate(Deliverable $deliverable): bool
    {
        return Auth::can('deliverables.update') && self::canView($deliverable) && self::clientScope() === null;
    }
}
