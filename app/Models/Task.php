<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Task extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $project_id = 0;
    public int $client_id = 0;
    public string $title = '';
    public ?string $description = null;
    public ?int $assignee_user_id = null;
    public ?int $reporter_user_id = null;
    public ?int $work_flow_role_id = null;
    public ?int $booking_id = null;
    public ?int $lead_id = null;
    public ?int $manager_user_id = null;
    public ?int $workflow_run_id = null;
    public ?int $workflow_run_stage_id = null;
    public ?string $completion_criteria = null;
    public ?string $estimated_hours = null;
    public ?string $submitted_at = null;
    public ?string $approved_at = null;
    public ?int $approved_by = null;
    public ?string $submission_note = null;
    public string $priority = 'medium';
    public string $status = 'todo';
    public ?string $start_date = null;
    public ?string $due_date = null;
    public int $progress = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $project_name = null;
    public ?string $project_code = null;
    public ?string $client_name = null;
    public ?string $assignee_name = null;
    public ?string $reporter_name = null;
    public ?string $status_name = null;
    public ?string $status_color = null;
    public ?int $status_closed = null;

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

    public static function paginate(
        int $organizationId,
        int $page,
        int $perPage,
        ?string $search,
        ?string $status,
        ?string $priority,
        ?int $projectId,
        ?int $assigneeId,
        ?int $onlyUserId
    ): array {
        [$where, $params] = self::filters($organizationId, $search, $status, $priority, $projectId, $assigneeId, $onlyUserId);
        $clause = implode(' AND ', $where);
        $total = (int) Database::query(
            'SELECT COUNT(*) FROM tasks t INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id WHERE ' . $clause,
            $params
        )->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . $clause . ' ORDER BY t.updated_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            'items' => array_map([self::class, 'hydrate'], $rows),
        ];
    }

    /** @return list<self> */
    public static function board(
        int $organizationId,
        ?string $search,
        ?string $status,
        ?string $priority,
        ?int $projectId,
        ?int $assigneeId,
        ?int $onlyUserId,
        int $limit = 300
    ): array {
        [$where, $params] = self::filters($organizationId, $search, $status, $priority, $projectId, $assigneeId, $onlyUserId);
        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY t.updated_at DESC LIMIT ' . (int) $limit,
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function forProject(int $projectId, int $organizationId, int $limit = 8): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE t.project_id = ? AND t.organization_id = ? ORDER BY t.updated_at DESC LIMIT ' . (int) $limit,
            [$projectId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function countForProject(int $projectId, int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM tasks WHERE project_id = ? AND organization_id = ?',
            [$projectId, $organizationId]
        )->fetchColumn();
    }

    public static function countVisible(int $organizationId, ?int $onlyUserId, bool $openOnly = false, ?int $assigneeId = null): int
    {
        [$where, $params] = self::filters($organizationId, null, null, null, null, $assigneeId, $onlyUserId);
        if ($openOnly) {
            $where[] = 'NOT EXISTS (
                SELECT 1 FROM task_statuses ts
                WHERE ts.organization_id = t.organization_id AND ts.slug = t.status AND ts.is_closed = 1
            )';
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM tasks t INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id WHERE ' . implode(' AND ', $where),
            $params
        )->fetchColumn();
    }

    /** @return array<string, int> */
    public static function countByStatus(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::filters($organizationId, null, null, null, null, null, $onlyUserId);
        $rows = Database::query(
            'SELECT t.status, COUNT(*) AS total
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY t.status',
            $params
        )->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<int, int> */
    public static function openCountsByAssignee(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::filters($organizationId, null, null, null, null, null, $onlyUserId);
        $where[] = 't.assignee_user_id IS NOT NULL';
        $where[] = 'NOT EXISTS (
            SELECT 1 FROM task_statuses ts
            WHERE ts.organization_id = t.organization_id AND ts.slug = t.status AND ts.is_closed = 1
        )';

        $rows = Database::query(
            'SELECT t.assignee_user_id, COUNT(*) AS total
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY t.assignee_user_id',
            $params
        )->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['assignee_user_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return list<self> */
    public static function dueOn(int $organizationId, string $date, ?int $assigneeId = null): array
    {
        $sql = self::selectSql() . ' WHERE t.organization_id = ? AND t.due_date = ?';
        $params = [$organizationId, $date];
        if ($assigneeId !== null && $assigneeId > 0) {
            $sql .= ' AND t.assignee_user_id = ?';
            $params[] = $assigneeId;
        }
        $sql .= ' ORDER BY t.priority DESC, t.title ASC';
        $rows = Database::query($sql, $params)->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function openDueBy(int $organizationId, string $throughDate, int $assigneeId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE t.organization_id = ? AND t.assignee_user_id = ?
              AND t.due_date IS NOT NULL AND t.due_date <= ?
              AND COALESCE(ts.is_closed, 0) = 0
             ORDER BY t.due_date ASC, t.priority DESC, t.title ASC
             LIMIT 30',
            [$organizationId, $assigneeId, $throughDate]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function forCalendar(int $organizationId, string $fromDate, string $toDate): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE t.organization_id = ?
              AND t.due_date IS NOT NULL
              AND t.due_date >= ?
              AND t.due_date <= ?
             ORDER BY t.due_date ASC, t.priority DESC, t.title ASC',
            [$organizationId, $fromDate, $toDate]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO tasks (
                organization_id, project_id, client_id, lead_id, title, description, completion_criteria,
                assignee_user_id, reporter_user_id, manager_user_id, work_flow_role_id, booking_id,
                workflow_run_id, workflow_run_stage_id, priority, status, start_date, due_date, progress,
                estimated_hours, submitted_at, approved_at, approved_by, submission_note, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['project_id'],
                $data['client_id'],
                $data['lead_id'] ?? null,
                $data['title'],
                $data['description'] ?? null,
                $data['completion_criteria'] ?? null,
                $data['assignee_user_id'] ?? null,
                $data['reporter_user_id'] ?? null,
                $data['manager_user_id'] ?? null,
                $data['work_flow_role_id'] ?? null,
                $data['booking_id'] ?? null,
                $data['workflow_run_id'] ?? null,
                $data['workflow_run_stage_id'] ?? null,
                $data['priority'] ?? 'medium',
                $data['status'] ?? 'todo',
                $data['start_date'] ?? null,
                $data['due_date'] ?? null,
                $data['progress'] ?? 0,
                $data['estimated_hours'] ?? null,
                $data['submitted_at'] ?? null,
                $data['approved_at'] ?? null,
                $data['approved_by'] ?? null,
                $data['submission_note'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'project_id', 'client_id', 'lead_id', 'title', 'description', 'completion_criteria',
            'assignee_user_id', 'manager_user_id', 'work_flow_role_id', 'booking_id',
            'workflow_run_id', 'workflow_run_stage_id', 'priority', 'status', 'start_date', 'due_date',
            'progress', 'estimated_hours', 'submitted_at', 'approved_at', 'approved_by', 'submission_note',
            'updated_at',
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
            'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM tasks WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    private static function selectSql(): string
    {
        return 'SELECT t.*, p.name AS project_name, p.code AS project_code, c.name AS client_name,
                       a.name AS assignee_name, r.name AS reporter_name,
                       ts.name AS status_name, ts.color AS status_color, ts.is_closed AS status_closed
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
                INNER JOIN clients c ON c.id = t.client_id AND c.organization_id = t.organization_id
                LEFT JOIN users a ON a.id = t.assignee_user_id AND a.organization_id = t.organization_id
                LEFT JOIN users r ON r.id = t.reporter_user_id AND r.organization_id = t.organization_id
                LEFT JOIN task_statuses ts ON ts.organization_id = t.organization_id AND ts.slug = t.status';
    }

    private static function filters(
        int $organizationId,
        ?string $search,
        ?string $status,
        ?string $priority,
        ?int $projectId,
        ?int $assigneeId,
        ?int $onlyUserId
    ): array {
        $where = ['t.organization_id = ?'];
        $params = [$organizationId];

        if ($search !== null && $search !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = '%' . $search . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }

        if ($priority !== null && $priority !== '') {
            $where[] = 't.priority = ?';
            $params[] = $priority;
        }

        if ($projectId !== null && $projectId > 0) {
            $where[] = 't.project_id = ?';
            $params[] = $projectId;
        }

        if ($assigneeId !== null && $assigneeId > 0) {
            $where[] = 't.assignee_user_id = ?';
            $params[] = $assigneeId;
        }

        if ($onlyUserId !== null) {
            $where[] = '(t.assignee_user_id = ? OR p.manager_user_id = ? OR EXISTS (
                SELECT 1 FROM project_members pm
                WHERE pm.project_id = t.project_id AND pm.user_id = ? AND pm.organization_id = t.organization_id
            ))';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }
}
