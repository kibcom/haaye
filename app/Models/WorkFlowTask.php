<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Workflow tasks share the operations `tasks` table.
 * Optional work_flow_role_id marks the production pipeline lane.
 */
final class WorkFlowTask extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $project_id = 0;
    public int $client_id = 0;
    public ?int $work_flow_role_id = null;
    public ?int $booking_id = null;
    public string $title = '';
    public ?string $description = null;
    public ?int $assignee_user_id = null;
    public string $status = 'todo';
    public ?string $due_date = null;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $project_name = null;
    public ?string $project_code = null;
    public ?string $role_name = null;
    public ?string $assignee_name = null;
    public ?string $client_name = null;

    public static function table(): string
    {
        return 'tasks';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE t.id = ? AND t.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /**
     * @return list<self>
     */
    public static function listFor(int $organizationId, ?int $onlyUserId, ?int $projectId = null, ?string $status = null): array
    {
        $where = ['t.organization_id = ?'];
        $params = [$organizationId];

        if ($onlyUserId !== null) {
            $where[] = '(
                p.manager_user_id = ?
                OR EXISTS (
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = t.project_id
                      AND pm.organization_id = t.organization_id
                      AND pm.user_id = ?
                )
            )';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        if ($projectId !== null && $projectId > 0) {
            $where[] = 't.project_id = ?';
            $params[] = $projectId;
        }

        if ($status !== null && $status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }

        $rows = Database::query(
            self::selectSql()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY FIELD(t.status, \'todo\', \'in_progress\', \'review\', \'done\'), t.due_date IS NULL, t.due_date ASC, t.id DESC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');
        Database::query(
            'INSERT INTO tasks (
                organization_id, project_id, client_id, title, description,
                assignee_user_id, reporter_user_id, work_flow_role_id, booking_id,
                priority, status, start_date, due_date, progress, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['project_id'],
                $data['client_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['assignee_user_id'] ?? null,
                $data['created_by'] ?? $data['reporter_user_id'] ?? null,
                $data['work_flow_role_id'] ?? null,
                $data['booking_id'] ?? null,
                $data['priority'] ?? 'medium',
                $data['status'] ?? 'todo',
                $data['start_date'] ?? null,
                $data['due_date'] ?? null,
                $data['progress'] ?? 0,
                $now,
                $data['updated_at'] ?? $now,
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'project_id', 'client_id', 'work_flow_role_id', 'booking_id', 'title', 'description',
            'assignee_user_id', 'status', 'due_date', 'updated_at',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $data[$column];
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $params[] = $organizationId;
        Database::query(
            'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query(
            'DELETE FROM tasks WHERE id = ? AND organization_id = ?',
            [$id, $organizationId]
        );
    }

    private static function selectSql(): string
    {
        return 'SELECT t.id, t.organization_id, t.project_id, t.client_id, t.work_flow_role_id, t.booking_id,
                    t.title, t.description, t.assignee_user_id, t.status, t.due_date,
                    t.reporter_user_id AS created_by, t.created_at, t.updated_at,
                    p.name AS project_name,
                    p.code AS project_code,
                    c.name AS client_name,
                    r.name AS role_name,
                    u.name AS assignee_name
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
                INNER JOIN clients c ON c.id = t.client_id AND c.organization_id = t.organization_id
                LEFT JOIN work_flow_roles r ON r.id = t.work_flow_role_id
                LEFT JOIN users u ON u.id = t.assignee_user_id';
    }
}
