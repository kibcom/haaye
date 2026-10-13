<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class WorkFlowRole extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $name = '';
    public ?string $description = null;
    public int $sort_order = 0;
    public int $is_active = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public int $task_count = 0;

    public static function table(): string
    {
        return 'work_flow_roles';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM work_flow_roles WHERE id = ? AND organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function listFor(int $organizationId, bool $activeOnly = false): array
    {
        $sql = 'SELECT r.*,
                    (SELECT COUNT(*) FROM tasks t WHERE t.work_flow_role_id = r.id) AS task_count
                FROM work_flow_roles r
                WHERE r.organization_id = ?';
        $params = [$organizationId];
        if ($activeOnly) {
            $sql .= ' AND r.is_active = 1';
        }
        $sql .= ' ORDER BY r.sort_order ASC, r.name ASC';

        return array_map([self::class, 'hydrate'], Database::query($sql, $params)->fetchAll());
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO work_flow_roles (organization_id, name, description, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['sort_order'] ?? 0,
                $data['is_active'] ?? 1,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        Database::query(
            'UPDATE work_flow_roles
             SET name = ?, description = ?, sort_order = ?, is_active = ?, updated_at = ?
             WHERE id = ? AND organization_id = ?',
            [
                $data['name'],
                $data['description'] ?? null,
                $data['sort_order'] ?? 0,
                $data['is_active'] ?? 1,
                $data['updated_at'],
                $id,
                $organizationId,
            ]
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query(
            'DELETE FROM work_flow_roles WHERE id = ? AND organization_id = ?',
            [$id, $organizationId]
        );
    }
}
