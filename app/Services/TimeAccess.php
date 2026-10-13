<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Project;
use App\Models\TimeEntry;

final class TimeAccess
{
    public static function canView(TimeEntry $entry): bool
    {
        if (!Auth::can('time.view')) {
            return false;
        }

        if ((int) $entry->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        if ((int) $entry->user_id === (int) Auth::id()) {
            return true;
        }

        if (!Auth::can('time.view_all')) {
            return false;
        }

        $project = Project::findInOrganization((int) $entry->project_id, (int) $entry->organization_id);

        return $project instanceof Project && ProjectAccess::canView($project);
    }

    public static function canEdit(TimeEntry $entry): bool
    {
        if (!self::canView($entry) || $entry->isRunning()) {
            return false;
        }

        if ((int) $entry->user_id === (int) Auth::id() && Auth::can('time.update')) {
            return true;
        }

        return Auth::can('time.manage');
    }

    public static function canDelete(TimeEntry $entry): bool
    {
        if (!self::canView($entry)) {
            return false;
        }

        if ($entry->isRunning()) {
            return (int) $entry->user_id === (int) Auth::id() && Auth::can('time.update');
        }

        return self::canEdit($entry);
    }

    /**
     * Entries the signed-in user is allowed to see.
     *
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public static function scope(int $organizationId): array
    {
        $where = ['te.organization_id = ?'];
        $params = [$organizationId];
        $viewerId = (int) Auth::id();

        if (!Auth::can('time.view_all')) {
            $where[] = 'te.user_id = ?';
            $params[] = $viewerId;

            return [$where, $params];
        }

        $onlyUserId = ProjectAccess::restrictToUserId();
        if ($onlyUserId !== null) {
            $where[] = '(te.user_id = ? OR EXISTS (
                SELECT 1 FROM projects p
                WHERE p.id = te.project_id AND p.organization_id = te.organization_id
                AND (p.manager_user_id = ? OR EXISTS (
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
                ))
            ))';
            $params[] = $viewerId;
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }
}
