<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Invoice;

final class FinanceReport
{
    /**
     * @return array{
     *     clients: list<array<string, mixed>>,
     *     hierarchy: list<array<string, mixed>>,
     *     done: list<array<string, mixed>>,
     *     sold: list<array<string, mixed>>,
     *     jobs: list<array<string, mixed>>
     * }
     */
    public static function build(int $organizationId, ?int $onlyUserId): array
    {
        return [
            'clients' => self::topClients($organizationId, $onlyUserId),
            'hierarchy' => self::hierarchy($organizationId, $onlyUserId),
            'done' => self::doneWork($organizationId, $onlyUserId),
            'sold' => self::soldWork($organizationId, $onlyUserId),
            'jobs' => self::frequentJobs($organizationId, $onlyUserId),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function topClients(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = Invoice::scope($organizationId, $onlyUserId);
        $rows = Database::query(
            'SELECT c.name AS client_name,
                    COUNT(DISTINCT i.id) AS invoices,
                    COALESCE(SUM(CASE WHEN i.status IN (\'sent\',\'partial\',\'paid\') THEN i.total ELSE 0 END), 0) AS sold,
                    COALESCE(SUM(CASE WHEN i.status IN (\'sent\',\'partial\',\'paid\') THEN i.paid_amount ELSE 0 END), 0) AS collected
             FROM invoices i
             INNER JOIN clients c ON c.id = i.client_id AND c.organization_id = i.organization_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY c.id, c.name
             HAVING sold > 0 OR collected > 0
             ORDER BY sold DESC, collected DESC, c.name ASC
             LIMIT 8',
            $params
        )->fetchAll();

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function hierarchy(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::projectScope($organizationId, $onlyUserId);
        $rows = Database::query(
            'SELECT c.name AS client_name, p.name AS project_name, p.code AS project_code,
                    COALESCE(ps.name, p.status) AS status_name,
                    COALESCE(task_counts.done_tasks, 0) AS done_tasks,
                    COALESCE(task_counts.tasks, 0) AS tasks,
                    COALESCE(sales.sold, 0) AS sold
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id AND c.organization_id = p.organization_id
             LEFT JOIN project_statuses ps ON ps.organization_id = p.organization_id AND ps.slug = p.status
             LEFT JOIN (
                SELECT t.project_id,
                       COUNT(*) AS tasks,
                       COALESCE(SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END), 0) AS done_tasks
                FROM tasks t
                LEFT JOIN task_statuses ts ON ts.organization_id = t.organization_id AND ts.slug = t.status
                WHERE t.organization_id = ?
                GROUP BY t.project_id
             ) task_counts ON task_counts.project_id = p.id
             LEFT JOIN (
                SELECT project_id,
                       COALESCE(SUM(CASE WHEN status IN (\'sent\',\'partial\',\'paid\') THEN total ELSE 0 END), 0) AS sold
                FROM invoices
                WHERE organization_id = ?
                GROUP BY project_id
             ) sales ON sales.project_id = p.id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.name ASC, sold DESC, p.name ASC',
            array_merge([$organizationId, $organizationId], $params)
        )->fetchAll();

        $tree = [];
        foreach ($rows as $row) {
            $client = (string) $row['client_name'];
            if (!isset($tree[$client])) {
                $tree[$client] = [
                    'client_name' => $client,
                    'projects' => 0,
                    'done_tasks' => 0,
                    'tasks' => 0,
                    'sold' => 0.0,
                    'items' => [],
                ];
            }
            $tree[$client]['projects']++;
            $tree[$client]['done_tasks'] += (int) $row['done_tasks'];
            $tree[$client]['tasks'] += (int) $row['tasks'];
            $tree[$client]['sold'] += (float) $row['sold'];
            $tree[$client]['items'][] = [
                'project_name' => (string) $row['project_name'],
                'project_code' => (string) ($row['project_code'] ?? ''),
                'status_name' => (string) $row['status_name'],
                'done_tasks' => (int) $row['done_tasks'],
                'tasks' => (int) $row['tasks'],
                'sold' => (float) $row['sold'],
            ];
        }

        $list = array_values($tree);
        usort($list, static fn (array $a, array $b): int => $b['sold'] <=> $a['sold']);

        return array_slice($list, 0, 8);
    }

    /** @return list<array<string, mixed>> */
    private static function doneWork(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::projectScope($organizationId, $onlyUserId);
        $rows = Database::query(
            'SELECT p.name AS project_name, p.code AS project_code, c.name AS client_name,
                    COALESCE(ps.name, p.status) AS status_name,
                    COALESCE(task_counts.done_tasks, 0) AS done_tasks,
                    COALESCE(task_counts.tasks, 0) AS tasks
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id AND c.organization_id = p.organization_id
             LEFT JOIN project_statuses ps ON ps.organization_id = p.organization_id AND ps.slug = p.status
             LEFT JOIN (
                SELECT t.project_id,
                       COUNT(*) AS tasks,
                       COALESCE(SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END), 0) AS done_tasks
                FROM tasks t
                LEFT JOIN task_statuses ts ON ts.organization_id = t.organization_id AND ts.slug = t.status
                WHERE t.organization_id = ?
                GROUP BY t.project_id
             ) task_counts ON task_counts.project_id = p.id
             WHERE ' . implode(' AND ', $where) . '
               AND COALESCE(task_counts.done_tasks, 0) > 0
             ORDER BY done_tasks DESC, tasks DESC, p.name ASC
             LIMIT 8',
            array_merge([$organizationId], $params)
        )->fetchAll();

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function soldWork(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = Invoice::scope($organizationId, $onlyUserId);
        $where[] = 'i.status IN (\'sent\',\'partial\',\'paid\')';
        $rows = Database::query(
            'SELECT ii.description AS work_name, c.name AS client_name,
                    COALESCE(p.name, \'No project\') AS project_name,
                    SUM(ii.quantity) AS quantity,
                    SUM(ii.line_total) AS amount,
                    COUNT(*) AS times
             FROM invoice_items ii
             INNER JOIN invoices i ON i.id = ii.invoice_id AND i.organization_id = ii.organization_id
             INNER JOIN clients c ON c.id = i.client_id AND c.organization_id = i.organization_id
             LEFT JOIN projects p ON p.id = i.project_id AND p.organization_id = i.organization_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY ii.description, c.id, c.name, p.id, p.name
             ORDER BY amount DESC, times DESC, ii.description ASC
             LIMIT 8',
            $params
        )->fetchAll();

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function frequentJobs(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::projectScope($organizationId, $onlyUserId);
        $rows = Database::query(
            'SELECT t.title AS job_name,
                    COUNT(*) AS times,
                    COALESCE(SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END), 0) AS done_times,
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR \', \') AS client_names,
                    GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR \', \') AS project_names
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
             INNER JOIN clients c ON c.id = t.client_id AND c.organization_id = t.organization_id
             LEFT JOIN task_statuses ts ON ts.organization_id = t.organization_id AND ts.slug = t.status
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY t.title
             ORDER BY times DESC, done_times DESC, t.title ASC
             LIMIT 8',
            $params
        )->fetchAll();

        return $rows;
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private static function projectScope(int $organizationId, ?int $onlyUserId): array
    {
        $where = ['p.organization_id = ?'];
        $params = [$organizationId];
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
