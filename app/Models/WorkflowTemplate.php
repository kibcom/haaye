<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class WorkflowTemplate extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $name = '';
    public ?string $description = null;
    public string $progression_mode = 'automatic';
    public int $is_active = 1;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?int $stage_count = null;

    public static function table(): string
    {
        return 'workflow_templates';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT t.*, (SELECT COUNT(*) FROM workflow_template_stages s WHERE s.template_id = t.id AND s.is_active = 1) AS stage_count
             FROM workflow_templates t WHERE t.id = ? AND t.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forOrganization(int $organizationId, bool $activeOnly = false): array
    {
        $sql = 'SELECT t.*, (SELECT COUNT(*) FROM workflow_template_stages s WHERE s.template_id = t.id AND s.is_active = 1) AS stage_count
                FROM workflow_templates t WHERE t.organization_id = ?';
        $params = [$organizationId];
        if ($activeOnly) {
            $sql .= ' AND t.is_active = 1';
        }
        $sql .= ' ORDER BY t.name ASC';
        $rows = Database::query($sql, $params)->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO workflow_templates (organization_id, name, description, progression_mode, is_active, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['progression_mode'] ?? 'automatic',
                $data['is_active'] ?? 1,
                $data['created_by'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = ['name', 'description', 'progression_mode', 'is_active', 'updated_at'];
        $fields = [];
        $params = [];
        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $fields[] = $column . ' = ?';
            $params[] = $data[$column];
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $params[] = $organizationId;
        Database::query(
            'UPDATE workflow_templates SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }
}
