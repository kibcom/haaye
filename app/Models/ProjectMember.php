<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectMember extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $project_id = 0;
    public int $user_id = 0;
    public string $member_role = 'member';
    public ?string $created_at = null;
    public ?string $user_name = null;
    public ?string $job_title = null;

    public static function table(): string
    {
        return 'project_members';
    }

    /** @return list<self> */
    public static function forProject(int $projectId, int $organizationId): array
    {
        $rows = Database::query(
            'SELECT pm.*, u.name AS user_name, u.job_title
             FROM project_members pm
             INNER JOIN users u ON u.id = pm.user_id AND u.organization_id = pm.organization_id
             WHERE pm.project_id = ? AND pm.organization_id = ?
             ORDER BY pm.member_role = \'manager\' DESC, u.name ASC',
            [$projectId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInProject(int $id, int $projectId, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT pm.*, u.name AS user_name, u.job_title
             FROM project_members pm
             INNER JOIN users u ON u.id = pm.user_id
             WHERE pm.id = ? AND pm.project_id = ? AND pm.organization_id = ?
             LIMIT 1',
            [$id, $projectId, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function roleFor(int $projectId, int $userId, int $organizationId): ?string
    {
        $row = Database::query(
            'SELECT member_role FROM project_members
             WHERE project_id = ? AND user_id = ? AND organization_id = ?
             LIMIT 1',
            [$projectId, $userId, $organizationId]
        )->fetch();

        return $row ? (string) $row['member_role'] : null;
    }

    public static function upsert(int $organizationId, int $projectId, int $userId, string $role): void
    {
        $existing = Database::query(
            'SELECT id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
            [$projectId, $userId]
        )->fetch();

        if ($existing) {
            Database::query(
                'UPDATE project_members SET member_role = ?, organization_id = ? WHERE id = ?',
                [$role, $organizationId, $existing['id']]
            );
            return;
        }

        Database::query(
            'INSERT INTO project_members (organization_id, project_id, user_id, member_role, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$organizationId, $projectId, $userId, $role, date('Y-m-d H:i:s')]
        );
    }

    public static function deleteInProject(int $id, int $projectId, int $organizationId): void
    {
        Database::query(
            'DELETE FROM project_members WHERE id = ? AND project_id = ? AND organization_id = ?',
            [$id, $projectId, $organizationId]
        );
    }
}
