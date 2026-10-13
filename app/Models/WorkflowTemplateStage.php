<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class WorkflowTemplateStage extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $template_id = 0;
    public string $name = '';
    public ?string $description = null;
    public ?string $instructions = null;
    public int $sort_order = 0;
    public ?int $work_flow_role_id = null;
    public ?int $default_assignee_user_id = null;
    public int $requires_approval = 1;
    public ?int $approver_user_id = null;
    public int $auto_advance = 1;
    public ?string $deliverables_json = null;
    public int $is_active = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $role_name = null;
    public ?string $assignee_name = null;

    public static function table(): string
    {
        return 'workflow_template_stages';
    }

    /** @return list<self> */
    public static function forTemplate(int $templateId, int $organizationId, bool $activeOnly = true): array
    {
        $sql = 'SELECT s.*, r.name AS role_name, u.name AS assignee_name
                FROM workflow_template_stages s
                LEFT JOIN work_flow_roles r ON r.id = s.work_flow_role_id
                LEFT JOIN users u ON u.id = s.default_assignee_user_id
                WHERE s.template_id = ? AND s.organization_id = ?';
        $params = [$templateId, $organizationId];
        if ($activeOnly) {
            $sql .= ' AND s.is_active = 1';
        }
        $sql .= ' ORDER BY s.sort_order ASC, s.id ASC';
        $rows = Database::query($sql, $params)->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM workflow_template_stages WHERE id = ? AND organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO workflow_template_stages (
                organization_id, template_id, name, description, instructions, sort_order,
                work_flow_role_id, default_assignee_user_id, requires_approval, approver_user_id,
                auto_advance, deliverables_json, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['template_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['instructions'] ?? null,
                $data['sort_order'] ?? 0,
                $data['work_flow_role_id'] ?? null,
                $data['default_assignee_user_id'] ?? null,
                $data['requires_approval'] ?? 1,
                $data['approver_user_id'] ?? null,
                $data['auto_advance'] ?? 1,
                $data['deliverables_json'] ?? null,
                $data['is_active'] ?? 1,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'name', 'description', 'instructions', 'sort_order', 'work_flow_role_id',
            'default_assignee_user_id', 'requires_approval', 'approver_user_id',
            'auto_advance', 'deliverables_json', 'is_active', 'updated_at',
        ];
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
            'UPDATE workflow_template_stages SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query(
            'DELETE FROM workflow_template_stages WHERE id = ? AND organization_id = ?',
            [$id, $organizationId]
        );
    }

    public static function nextSortOrder(int $templateId, int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM workflow_template_stages WHERE template_id = ? AND organization_id = ?',
            [$templateId, $organizationId]
        )->fetchColumn();
    }
}
