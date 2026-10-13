<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Project;
use App\Models\ProjectMember;

final class ProjectAccess
{
    public static function seesAll(): bool
    {
        return Auth::can('projects.view_all');
    }

    public static function restrictToUserId(): ?int
    {
        if (self::seesAll()) {
            return null;
        }

        return Auth::id();
    }

    public static function canView(Project $project): bool
    {
        if (!Auth::can('projects.view') && !Auth::can('projects.view_all')) {
            return false;
        }

        if ((int) $project->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        if (self::seesAll()) {
            return true;
        }

        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        if ((int) $project->manager_user_id === $userId) {
            return true;
        }

        return ProjectMember::roleFor((int) $project->id, $userId, (int) $project->organization_id) !== null;
    }

    public static function isManager(Project $project): bool
    {
        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        if ((int) $project->manager_user_id === $userId) {
            return true;
        }

        return ProjectMember::roleFor((int) $project->id, $userId, (int) $project->organization_id) === 'manager';
    }

    public static function canEdit(Project $project): bool
    {
        return Auth::can('projects.update') && self::canView($project) && (self::seesAll() || self::isManager($project));
    }

    public static function canManageMembers(Project $project): bool
    {
        return Auth::can('projects.members.manage') && self::canView($project) && (self::seesAll() || self::isManager($project));
    }

    public static function canManageMilestones(Project $project): bool
    {
        return Auth::can('projects.milestones.manage') && self::canView($project) && (self::seesAll() || self::isManager($project));
    }
}
