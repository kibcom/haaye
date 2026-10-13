<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Deliverable extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $project_id = 0;
    public ?int $task_id = null;
    public int $client_id = 0;
    public string $name = '';
    public ?string $description = null;
    public ?string $due_date = null;
    public ?int $assignee_user_id = null;
    public string $status = 'draft';
    public int $version = 1;
    public string $internal_approval_status = 'none';
    public string $client_approval_status = 'none';
    public ?string $original_name = null;
    public ?string $stored_path = null;
    public ?string $mime_type = null;
    public ?int $size_bytes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $project_name = null;
    public ?string $client_name = null;
    public ?string $task_title = null;
    public ?string $assignee_name = null;
    public ?string $status_name = null;
    public ?string $status_color = null;

    public static function table(): string
    {
        return 'deliverables';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE d.id = ? AND d.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function paginate(
        int $organizationId,
        int $page,
        int $perPage,
        ?string $search,
        ?string $status,
        ?int $projectId,
        ?int $onlyUserId,
        ?int $onlyClientId,
        ?array $clientStatuses
    ): array {
        [$where, $params] = self::filters($organizationId, $search, $status, $projectId, $onlyUserId, $onlyClientId, $clientStatuses);
        $clause = implode(' AND ', $where);
        $total = (int) Database::query(
            'SELECT COUNT(*) FROM deliverables d INNER JOIN projects p ON p.id = d.project_id AND p.organization_id = d.organization_id WHERE ' . $clause,
            $params
        )->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            self::selectSql() . ' WHERE ' . $clause . ' ORDER BY d.updated_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
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
    public static function forProject(int $projectId, int $organizationId, int $limit = 8): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE d.project_id = ? AND d.organization_id = ? ORDER BY d.updated_at DESC LIMIT ' . (int) $limit,
            [$projectId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO deliverables (
                organization_id, project_id, task_id, client_id, name, description, due_date, assignee_user_id,
                status, version, internal_approval_status, client_approval_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['project_id'],
                $data['task_id'] ?? null,
                $data['client_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['due_date'] ?? null,
                $data['assignee_user_id'] ?? null,
                $data['status'] ?? 'draft',
                $data['version'] ?? 1,
                $data['internal_approval_status'] ?? 'none',
                $data['client_approval_status'] ?? 'none',
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'project_id', 'task_id', 'client_id', 'name', 'description', 'due_date', 'assignee_user_id',
            'status', 'version', 'internal_approval_status', 'client_approval_status',
            'original_name', 'stored_path', 'mime_type', 'size_bytes', 'updated_at',
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
        Database::query('UPDATE deliverables SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?', $params);
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM deliverables WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    /** @return list<self> */
    public static function forClientPortal(int $organizationId, int $clientId, bool $filesOnly = false, bool $actionOnly = false, int $limit = 50): array
    {
        $sql = self::selectSql() . " WHERE d.organization_id = ? AND d.client_id = ? AND d.status IN ('client_review','changes_requested','approved','delivered')";
        if ($filesOnly) {
            $sql .= " AND d.stored_path IS NOT NULL AND d.stored_path <> ''";
        }
        if ($actionOnly) {
            $sql .= " AND d.status IN ('client_review','changes_requested')";
        }
        $sql .= ' ORDER BY d.updated_at DESC LIMIT ' . (int) $limit;
        $rows = Database::query($sql, [$organizationId, $clientId])->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    private static function selectSql(): string
    {
        return 'SELECT d.*, p.name AS project_name, c.name AS client_name, t.title AS task_title, u.name AS assignee_name,
                       ds.name AS status_name, ds.color AS status_color
                FROM deliverables d
                INNER JOIN projects p ON p.id = d.project_id AND p.organization_id = d.organization_id
                INNER JOIN clients c ON c.id = d.client_id AND c.organization_id = d.organization_id
                LEFT JOIN tasks t ON t.id = d.task_id AND t.organization_id = d.organization_id
                LEFT JOIN users u ON u.id = d.assignee_user_id AND u.organization_id = d.organization_id
                LEFT JOIN deliverable_statuses ds ON ds.organization_id = d.organization_id AND ds.slug = d.status';
    }

    private static function filters(
        int $organizationId,
        ?string $search,
        ?string $status,
        ?int $projectId,
        ?int $onlyUserId,
        ?int $onlyClientId,
        ?array $clientStatuses
    ): array {
        $where = ['d.organization_id = ?'];
        $params = [$organizationId];

        if ($search !== null && $search !== '') {
            $where[] = 'd.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'd.status = ?';
            $params[] = $status;
        }
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'd.project_id = ?';
            $params[] = $projectId;
        }
        if ($onlyClientId !== null) {
            $where[] = 'd.client_id = ?';
            $params[] = $onlyClientId;
        }
        if ($clientStatuses !== null && $clientStatuses !== []) {
            $marks = implode(',', array_fill(0, count($clientStatuses), '?'));
            $where[] = 'd.status IN (' . $marks . ')';
            foreach ($clientStatuses as $slug) {
                $params[] = $slug;
            }
        }
        if ($onlyUserId !== null) {
            $where[] = '(d.assignee_user_id = ? OR p.manager_user_id = ? OR EXISTS (
                SELECT 1 FROM project_members pm
                WHERE pm.project_id = d.project_id AND pm.user_id = ? AND pm.organization_id = d.organization_id
            ))';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }
}
