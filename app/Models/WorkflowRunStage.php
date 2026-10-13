<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class WorkflowRunStage extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $run_id = 0;
    public ?int $template_stage_id = null;
    public string $name = '';
    public int $sort_order = 0;
    public string $status = 'pending';
    public ?int $task_id = null;
    public ?int $work_flow_role_id = null;
    public ?int $assignee_user_id = null;
    public int $requires_approval = 1;
    public ?int $approver_user_id = null;
    public int $auto_advance = 1;
    public ?string $instructions = null;
    public ?string $deliverables_json = null;
    public ?string $activated_at = null;
    public ?string $submitted_at = null;
    public ?string $approved_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $assignee_name = null;
    public ?string $task_title = null;
    public ?string $task_status = null;

    public static function table(): string
    {
        return 'workflow_run_stages';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE s.id = ? AND s.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function findByTask(int $taskId, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE s.task_id = ? AND s.organization_id = ? LIMIT 1',
            [$taskId, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forRun(int $runId, int $organizationId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE s.run_id = ? AND s.organization_id = ? ORDER BY s.sort_order ASC, s.id ASC',
            [$runId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO workflow_run_stages (
                organization_id, run_id, template_stage_id, name, sort_order, status, task_id,
                work_flow_role_id, assignee_user_id, requires_approval, approver_user_id, auto_advance,
                instructions, deliverables_json, activated_at, submitted_at, approved_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['run_id'],
                $data['template_stage_id'] ?? null,
                $data['name'],
                $data['sort_order'] ?? 0,
                $data['status'] ?? 'pending',
                $data['task_id'] ?? null,
                $data['work_flow_role_id'] ?? null,
                $data['assignee_user_id'] ?? null,
                $data['requires_approval'] ?? 1,
                $data['approver_user_id'] ?? null,
                $data['auto_advance'] ?? 1,
                $data['instructions'] ?? null,
                $data['deliverables_json'] ?? null,
                $data['activated_at'] ?? null,
                $data['submitted_at'] ?? null,
                $data['approved_at'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'name', 'sort_order', 'status', 'task_id', 'work_flow_role_id', 'assignee_user_id',
            'requires_approval', 'approver_user_id', 'auto_advance', 'instructions', 'deliverables_json',
            'activated_at', 'submitted_at', 'approved_at', 'updated_at',
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
            'UPDATE workflow_run_stages SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    private static function selectSql(): string
    {
        return 'SELECT s.*, u.name AS assignee_name, t.title AS task_title, t.status AS task_status
                FROM workflow_run_stages s
                LEFT JOIN users u ON u.id = s.assignee_user_id
                LEFT JOIN tasks t ON t.id = s.task_id';
    }
}
