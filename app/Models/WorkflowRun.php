<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class WorkflowRun extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $template_id = 0;
    public int $project_id = 0;
    public int $client_id = 0;
    public ?int $lead_id = null;
    public string $name = '';
    public string $status = 'active';
    public string $progression_mode = 'automatic';
    public ?int $current_stage_id = null;
    public ?int $started_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $completed_at = null;
    public ?string $project_name = null;
    public ?string $client_name = null;
    public ?string $template_name = null;
    public ?string $current_stage_name = null;

    public static function table(): string
    {
        return 'workflow_runs';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE r.id = ? AND r.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forProject(int $projectId, int $organizationId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE r.project_id = ? AND r.organization_id = ? ORDER BY r.created_at DESC',
            [$projectId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function activeForOrganization(int $organizationId, int $limit = 50): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE r.organization_id = ? AND r.status IN (\'active\', \'paused\')
             ORDER BY r.updated_at DESC LIMIT ' . (int) $limit,
            [$organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO workflow_runs (
                organization_id, template_id, project_id, client_id, lead_id, name, status,
                progression_mode, current_stage_id, started_by, created_at, updated_at, completed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['template_id'],
                $data['project_id'],
                $data['client_id'],
                $data['lead_id'] ?? null,
                $data['name'],
                $data['status'] ?? 'active',
                $data['progression_mode'] ?? 'automatic',
                $data['current_stage_id'] ?? null,
                $data['started_by'] ?? null,
                $data['created_at'],
                $data['updated_at'],
                $data['completed_at'] ?? null,
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = ['name', 'status', 'progression_mode', 'current_stage_id', 'updated_at', 'completed_at'];
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
            'UPDATE workflow_runs SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function countActive(int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM workflow_runs WHERE organization_id = ? AND status = ?',
            [$organizationId, 'active']
        )->fetchColumn();
    }

    private static function selectSql(): string
    {
        return 'SELECT r.*, p.name AS project_name, c.name AS client_name, t.name AS template_name,
                       rs.name AS current_stage_name
                FROM workflow_runs r
                INNER JOIN projects p ON p.id = r.project_id
                INNER JOIN clients c ON c.id = r.client_id
                INNER JOIN workflow_templates t ON t.id = r.template_id
                LEFT JOIN workflow_run_stages rs ON rs.id = r.current_stage_id';
    }
}
