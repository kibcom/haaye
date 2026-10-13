<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TimeEntry extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $user_id = 0;
    public int $project_id = 0;
    public ?int $task_id = null;
    public ?int $client_id = null;
    public string $started_at = '';
    public ?string $ended_at = null;
    public ?int $duration_minutes = null;
    public ?string $note = null;
    public int $billable = 1;
    public string $source = 'manual';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $user_name = null;
    public ?string $project_name = null;
    public ?string $project_code = null;
    public ?string $task_title = null;
    public ?string $client_name = null;

    public static function table(): string
    {
        return 'time_entries';
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE te.id = ? AND te.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function runningFor(int $organizationId, int $userId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE te.organization_id = ? AND te.user_id = ? AND te.ended_at IS NULL ORDER BY te.id DESC LIMIT 1',
            [$organizationId, $userId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /**
     * @param array{0: list<string>, 1: list<mixed>} $scope
     * @return list<self>
     */
    public static function forTimesheet(array $scope, int $userId, string $from, string $toExclusive): array
    {
        [$where, $params] = $scope;
        $where[] = 'te.user_id = ?';
        $where[] = 'te.started_at >= ?';
        $where[] = 'te.started_at < ?';
        $params[] = $userId;
        $params[] = $from;
        $params[] = $toExclusive;

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY te.started_at ASC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function forTask(int $organizationId, int $taskId, int $limit = 8): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE te.organization_id = ? AND te.task_id = ? ORDER BY te.started_at DESC LIMIT ' . (int) $limit,
            [$organizationId, $taskId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function minutesForTask(int $organizationId, int $taskId): int
    {
        return (int) Database::query(
            'SELECT COALESCE(SUM(duration_minutes), 0) FROM time_entries WHERE organization_id = ? AND task_id = ? AND ended_at IS NOT NULL',
            [$organizationId, $taskId]
        )->fetchColumn();
    }

    /**
     * @param array{0: list<string>, 1: list<mixed>} $scope
     * @return list<array<string, mixed>>
     */
    public static function grouped(array $scope, string $from, string $toExclusive, string $group, ?int $userId, ?int $projectId): array
    {
        $select = match ($group) {
            'user' => 'te.user_id AS bucket_id, u.name AS bucket_name',
            'project' => 'te.project_id AS bucket_id, p.name AS bucket_name',
            'task' => 'te.task_id AS bucket_id, COALESCE(t.title, \'No task\') AS bucket_name',
            default => 'te.user_id AS bucket_id, u.name AS bucket_name',
        };

        [$where, $params] = $scope;
        $where[] = 'te.started_at >= ?';
        $where[] = 'te.started_at < ?';
        $where[] = 'te.ended_at IS NOT NULL';
        $params[] = $from;
        $params[] = $toExclusive;

        if ($userId !== null && $userId > 0) {
            $where[] = 'te.user_id = ?';
            $params[] = $userId;
        }
        if ($projectId !== null && $projectId > 0) {
            $where[] = 'te.project_id = ?';
            $params[] = $projectId;
        }

        return Database::query(
            'SELECT ' . $select . ',
                COALESCE(SUM(te.duration_minutes), 0) AS minutes,
                COALESCE(SUM(CASE WHEN te.billable = 1 THEN te.duration_minutes ELSE 0 END), 0) AS billable_minutes
             FROM time_entries te
             INNER JOIN users u ON u.id = te.user_id
             INNER JOIN projects p ON p.id = te.project_id
             LEFT JOIN tasks t ON t.id = te.task_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY bucket_id, bucket_name
             ORDER BY minutes DESC, bucket_name ASC',
            $params
        )->fetchAll();
    }

    /**
     * @param array{0: list<string>, 1: list<mixed>} $scope
     * @return array<int, array{minutes: int, billable_minutes: int}>
     */
    public static function minutesByUser(array $scope, string $from, string $toExclusive): array
    {
        $map = [];
        foreach (self::grouped($scope, $from, $toExclusive, 'user', null, null) as $row) {
            $map[(int) $row['bucket_id']] = [
                'minutes' => (int) $row['minutes'],
                'billable_minutes' => (int) $row['billable_minutes'],
            ];
        }

        return $map;
    }

    /**
     * @param array{0: list<string>, 1: list<mixed>} $scope
     */
    public static function sumMinutes(array $scope, string $from, string $toExclusive, ?int $userId = null, ?int $projectId = null): int
    {
        [$where, $params] = $scope;
        $where[] = 'te.started_at >= ?';
        $where[] = 'te.started_at < ?';
        $where[] = 'te.ended_at IS NOT NULL';
        $params[] = $from;
        $params[] = $toExclusive;
        if ($userId !== null) {
            $where[] = 'te.user_id = ?';
            $params[] = $userId;
        }
        if ($projectId !== null) {
            $where[] = 'te.project_id = ?';
            $params[] = $projectId;
        }

        return (int) Database::query(
            'SELECT COALESCE(SUM(te.duration_minutes), 0)
             FROM time_entries te
             WHERE ' . implode(' AND ', $where),
            $params
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO time_entries (
                organization_id, user_id, project_id, task_id, client_id,
                started_at, ended_at, duration_minutes, note, billable, source, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['user_id'],
                $data['project_id'],
                $data['task_id'] ?? null,
                $data['client_id'] ?? null,
                $data['started_at'],
                $data['ended_at'] ?? null,
                $data['duration_minutes'] ?? null,
                $data['note'] ?? null,
                $data['billable'] ?? 1,
                $data['source'] ?? 'manual',
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'user_id', 'project_id', 'task_id', 'client_id', 'started_at', 'ended_at',
            'duration_minutes', 'note', 'billable', 'updated_at',
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
            'UPDATE time_entries SET ' . implode(', ', $sets) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteById(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM time_entries WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    private static function selectSql(): string
    {
        return 'SELECT te.*, u.name AS user_name, p.name AS project_name, p.code AS project_code,
            t.title AS task_title, c.name AS client_name
            FROM time_entries te
            INNER JOIN users u ON u.id = te.user_id
            INNER JOIN projects p ON p.id = te.project_id
            LEFT JOIN tasks t ON t.id = te.task_id
            LEFT JOIN clients c ON c.id = te.client_id';
    }
}
