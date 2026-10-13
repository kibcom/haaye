<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Project extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $client_id = 0;
    public string $name = '';
    public ?string $code = null;
    public ?string $description = null;
    public string $status = 'planning';
    public string $priority = 'medium';
    public ?string $start_date = null;
    public ?string $due_date = null;
    public ?string $budget = null;
    public string $billing_type = 'fixed';
    public ?int $manager_user_id = null;
    public int $progress = 0;
    public ?int $task_total = null;
    public int $task_done = 0;
    public string $payment_status = 'unpaid';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $client_name = null;
    public ?string $manager_name = null;
    public ?string $status_name = null;
    public ?string $status_color = null;
    public ?int $status_closed = null;
    public ?int $member_count = null;
    public ?int $milestone_count = null;

    public static function table(): string
    {
        return 'projects';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE p.id = ? AND p.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();
        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forClient(int $clientId, int $organizationId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE p.client_id = ? AND p.organization_id = ? ORDER BY p.due_date IS NULL, p.due_date ASC, p.name ASC',
            [$clientId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function paginate(
        int $organizationId,
        int $page,
        int $perPage,
        ?string $search,
        ?string $status,
        ?string $priority,
        ?int $clientId,
        ?int $onlyUserId,
        ?string $paymentStatus = null
    ): array {
        [$where, $params] = self::filters($organizationId, $search, $status, $priority, $clientId, $onlyUserId, $paymentStatus);
        $clause = implode(' AND ', $where);
        $total = (int) Database::query('SELECT COUNT(*) FROM projects p WHERE ' . $clause, $params)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . $clause . ' ORDER BY p.updated_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'items' => array_map([self::class, 'hydrate'], $rows),
        ];
    }

    public static function countVisible(int $organizationId, ?int $onlyUserId, bool $openOnly = false): int
    {
        [$where, $params] = self::filters($organizationId, null, null, null, null, $onlyUserId);
        if ($openOnly) {
            $where[] = 'NOT EXISTS (
                SELECT 1 FROM project_statuses ps
                WHERE ps.organization_id = p.organization_id AND ps.slug = p.status AND ps.is_closed = 1
            )';
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM projects p WHERE ' . implode(' AND ', $where),
            $params
        )->fetchColumn();
    }

    /** @return list<self> */
    public static function optionsVisible(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::filters($organizationId, null, null, null, null, $onlyUserId);
        $rows = Database::query(
            'SELECT p.*, c.name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id AND c.organization_id = p.organization_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY p.name ASC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function countForClient(int $clientId, int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM projects WHERE client_id = ? AND organization_id = ?',
            [$clientId, $organizationId]
        )->fetchColumn();
    }

    public static function codeExists(int $organizationId, string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM projects WHERE organization_id = ? AND code = ?';
        $params = [$organizationId, $code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return Database::query($sql . ' LIMIT 1', $params)->fetch() !== false;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO projects (
                organization_id, client_id, name, code, description, status, priority,
                start_date, due_date, budget, billing_type, manager_user_id, progress,
                payment_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['client_id'],
                $data['name'],
                $data['code'] ?? null,
                $data['description'] ?? null,
                $data['status'] ?? 'planning',
                $data['priority'] ?? 'medium',
                $data['start_date'] ?? null,
                $data['due_date'] ?? null,
                $data['budget'] ?? null,
                $data['billing_type'] ?? 'fixed',
                $data['manager_user_id'] ?? null,
                $data['progress'] ?? 0,
                $data['payment_status'] ?? 'unpaid',
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public function taskPercent(): int
    {
        if ($this->task_total === null) {
            return max(0, min(100, $this->progress));
        }
        if ($this->task_total < 1) {
            return 0;
        }

        return (int) round($this->task_done * 100 / $this->task_total);
    }

    public function taskCaption(): string
    {
        if ($this->task_total === null || $this->task_total < 1) {
            return 'No tasks';
        }

        return (int) $this->task_done . ' of ' . (int) $this->task_total . ' tasks';
    }

    public static function syncProgress(int $organizationId, int $projectId): void
    {
        $row = Database::query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END), 0) AS done
             FROM tasks t
             LEFT JOIN task_statuses ts
                ON ts.organization_id = t.organization_id AND ts.slug = t.status
             WHERE t.organization_id = ? AND t.project_id = ?',
            [$organizationId, $projectId]
        )->fetch();
        $total = (int) ($row['total'] ?? 0);
        $done = (int) ($row['done'] ?? 0);
        $progress = $total < 1 ? 0 : (int) round($done * 100 / $total);
        Database::query(
            'UPDATE projects SET progress = ? WHERE id = ? AND organization_id = ?',
            [$progress, $projectId, $organizationId]
        );
    }

    public static function syncOrganization(int $organizationId): void
    {
        $rows = Database::query('SELECT id FROM projects WHERE organization_id = ?', [$organizationId])->fetchAll();
        foreach ($rows as $row) {
            self::syncProgress($organizationId, (int) $row['id']);
        }
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $fields = [];
        $params = [];
        foreach ($data as $column => $value) {
            $fields[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $params[] = $organizationId;
        Database::query(
            'UPDATE projects SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    private static function selectSql(): string
    {
        return 'SELECT p.*, c.name AS client_name, u.name AS manager_name,
                       ps.name AS status_name, ps.color AS status_color, ps.is_closed AS status_closed,
                       (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id AND pm.organization_id = p.organization_id) AS member_count,
                       (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.organization_id = p.organization_id) AS milestone_count,
                       (SELECT COUNT(*) FROM tasks tk WHERE tk.project_id = p.id AND tk.organization_id = p.organization_id) AS task_total,
                       (SELECT COUNT(*) FROM tasks tk
                            INNER JOIN task_statuses tsx ON tsx.organization_id = tk.organization_id AND tsx.slug = tk.status AND tsx.is_closed = 1
                            WHERE tk.project_id = p.id AND tk.organization_id = p.organization_id) AS task_done
                FROM projects p
                INNER JOIN clients c ON c.id = p.client_id AND c.organization_id = p.organization_id
                LEFT JOIN users u ON u.id = p.manager_user_id AND u.organization_id = p.organization_id
                LEFT JOIN project_statuses ps ON ps.organization_id = p.organization_id AND ps.slug = p.status';
    }

    private static function filters(
        int $organizationId,
        ?string $search,
        ?string $status,
        ?string $priority,
        ?int $clientId,
        ?int $onlyUserId,
        ?string $paymentStatus = null
    ): array {
        $where = ['p.organization_id = ?'];
        $params = [$organizationId];

        if ($search !== null && $search !== '') {
            $where[] = '(p.name LIKE ? OR p.code LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== null && $status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($priority !== null && $priority !== '') {
            $where[] = 'p.priority = ?';
            $params[] = $priority;
        }

        if ($paymentStatus !== null && $paymentStatus !== '') {
            $where[] = 'p.payment_status = ?';
            $params[] = $paymentStatus;
        }

        if ($clientId !== null && $clientId > 0) {
            $where[] = 'p.client_id = ?';
            $params[] = $clientId;
        }

        if ($onlyUserId !== null) {
            $where[] = '(p.manager_user_id = ? OR EXISTS (
                SELECT 1 FROM project_members pm
                WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
            ))';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }
}
