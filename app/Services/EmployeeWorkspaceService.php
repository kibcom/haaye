<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Task;

final class EmployeeWorkspaceService
{
    /**
     * @return array{
     *   counts: array<string,int>,
     *   due_today: list<Task>,
     *   overdue: list<Task>,
     *   in_progress: list<Task>,
     *   changes_requested: list<Task>,
     *   submitted: list<Task>,
     *   upcoming: list<Task>
     * }
     */
    public function forUser(int $organizationId, int $userId): array
    {
        $today = date('Y-m-d');
        $mine = static function (string $extra = '', array $params = []) use ($organizationId, $userId): array {
            $sql = Task::class;
            unset($sql);
            $rows = Database::query(
                'SELECT t.*, p.name AS project_name, p.code AS project_code, c.name AS client_name,
                        a.name AS assignee_name, r.name AS reporter_name,
                        ts.name AS status_name, ts.color AS status_color, ts.is_closed AS status_closed
                 FROM tasks t
                 INNER JOIN projects p ON p.id = t.project_id AND p.organization_id = t.organization_id
                 INNER JOIN clients c ON c.id = t.client_id AND c.organization_id = t.organization_id
                 LEFT JOIN users a ON a.id = t.assignee_user_id
                 LEFT JOIN users r ON r.id = t.reporter_user_id
                 LEFT JOIN task_statuses ts ON ts.organization_id = t.organization_id AND ts.slug = t.status
                 WHERE t.organization_id = ? AND t.assignee_user_id = ? ' . $extra . '
                 ORDER BY t.due_date IS NULL, t.due_date ASC, t.priority DESC
                 LIMIT 40',
                array_merge([$organizationId, $userId], $params)
            )->fetchAll();

            return array_map([Task::class, 'hydrate'], $rows);
        };

        $count = static function (string $extra, array $params = []) use ($organizationId, $userId): int {
            return (int) Database::query(
                'SELECT COUNT(*) FROM tasks t
                 WHERE t.organization_id = ? AND t.assignee_user_id = ? ' . $extra,
                array_merge([$organizationId, $userId], $params)
            )->fetchColumn();
        };

        $open = " AND COALESCE((SELECT ts.is_closed FROM task_statuses ts WHERE ts.organization_id = t.organization_id AND ts.slug = t.status LIMIT 1), 0) = 0";

        return [
            'counts' => [
                'mine' => $count(''),
                'due_today' => $count(' AND t.due_date = ?' . $open, [$today]),
                'overdue' => $count(' AND t.due_date IS NOT NULL AND t.due_date < ?' . $open, [$today]),
                'in_progress' => $count(" AND t.status = 'in_progress'"),
                'changes_requested' => $count(" AND t.status = 'changes_requested'"),
                'submitted' => $count(" AND t.status IN ('submitted','resubmitted','review')"),
                'approved' => $count(" AND t.status IN ('approved','completed')"),
                'upcoming' => $count(' AND t.due_date IS NOT NULL AND t.due_date > ?' . $open, [$today]),
            ],
            'due_today' => $mine(' AND t.due_date = ?' . $open, [$today]),
            'overdue' => $mine(' AND t.due_date IS NOT NULL AND t.due_date < ?' . $open, [$today]),
            'in_progress' => $mine(" AND t.status = 'in_progress'"),
            'changes_requested' => $mine(" AND t.status = 'changes_requested'"),
            'submitted' => $mine(" AND t.status IN ('submitted','resubmitted','review')"),
            'upcoming' => $mine(' AND t.due_date IS NOT NULL AND t.due_date > ?' . $open, [$today]),
        ];
    }
}
