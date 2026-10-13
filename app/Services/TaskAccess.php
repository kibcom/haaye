<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;

final class TaskAccess
{
    /** @var array<int, Project|null> */
    private static array $projects = [];

    /** @var array<string, ?string> */
    private static array $roles = [];

    public static function restrictToUserId(): ?int
    {
        if (ProjectAccess::seesAll()) {
            return null;
        }

        return Auth::id();
    }

    public static function canView(Task $task): bool
    {
        if (!Auth::can('tasks.view')) {
            return false;
        }

        if ((int) $task->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        if (ProjectAccess::seesAll()) {
            return true;
        }

        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }

        if ((int) $task->assignee_user_id === $userId) {
            return true;
        }

        $project = self::project($task);
        if (!$project instanceof Project) {
            return false;
        }

        if ((int) $project->manager_user_id === $userId) {
            return true;
        }

        return self::memberRole((int) $project->id, $userId, (int) $project->organization_id) !== null;
    }

    private static function project(Task $task): ?Project
    {
        $projectId = (int) $task->project_id;
        if (!array_key_exists($projectId, self::$projects)) {
            self::$projects[$projectId] = Project::findInOrganization($projectId, (int) $task->organization_id);
        }

        return self::$projects[$projectId];
    }

    private static function memberRole(int $projectId, int $userId, int $organizationId): ?string
    {
        $key = $projectId . ':' . $userId;
        if (!array_key_exists($key, self::$roles)) {
            self::$roles[$key] = ProjectMember::roleFor($projectId, $userId, $organizationId);
        }

        return self::$roles[$key];
    }

    public static function canCreate(Project $project): bool
    {
        return Auth::can('tasks.create') && ProjectAccess::canView($project);
    }

    public static function canManage(Task $task): bool
    {
        return Auth::can('tasks.update') && self::canView($task);
    }

    public static function canAssign(Task $task): bool
    {
        return Auth::can('tasks.assign') && self::canView($task);
    }

    public static function canAssignOnProject(Project $project): bool
    {
        return Auth::can('tasks.assign') && ProjectAccess::canView($project);
    }

    public static function canChangeStatus(Task $task): bool
    {
        if (!self::canView($task)) {
            return false;
        }

        if (Auth::can('tasks.update')) {
            return true;
        }

        if (!Auth::can('tasks.work')) {
            return false;
        }

        $userId = Auth::id();
        return $userId !== null && (int) $task->assignee_user_id === $userId;
    }

    public static function canComment(Task $task): bool
    {
        return Auth::can('tasks.comment') && self::canView($task);
    }

    public static function canUpload(Task $task): bool
    {
        return Auth::can('tasks.attachments') && self::canView($task);
    }

    public static function canDeleteComment(Task $task, ?int $authorId): bool
    {
        if (!self::canView($task)) {
            return false;
        }

        $userId = Auth::id();
        if ($userId !== null && $authorId !== null && $authorId === $userId && Auth::can('tasks.comment')) {
            return true;
        }

        return self::canManage($task);
    }

    public static function canDeleteAttachment(Task $task, ?int $uploaderId): bool
    {
        if (!self::canView($task)) {
            return false;
        }

        $userId = Auth::id();
        if ($userId !== null && $uploaderId !== null && $uploaderId === $userId && Auth::can('tasks.attachments')) {
            return true;
        }

        return self::canManage($task);
    }
}
