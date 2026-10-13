<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TaskStatus extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $name = '';
    public string $slug = '';
    public string $color = '#64748B';
    public int $sort_order = 0;
    public int $is_closed = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function table(): string
    {
        return 'task_statuses';
    }

    /** @return list<self> */
    public static function forOrganization(int $organizationId): array
    {
        $rows = Database::query(
            'SELECT * FROM task_statuses WHERE organization_id = ? ORDER BY sort_order ASC, id ASC',
            [$organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM task_statuses WHERE id = ? AND organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function findBySlug(int $organizationId, string $slug): ?self
    {
        $row = Database::query(
            'SELECT * FROM task_statuses WHERE organization_id = ? AND slug = ? LIMIT 1',
            [$organizationId, $slug]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function ensureDefaults(int $organizationId): void
    {
        $count = (int) Database::query(
            'SELECT COUNT(*) FROM task_statuses WHERE organization_id = ?',
            [$organizationId]
        )->fetchColumn();

        if ($count > 0) {
            self::ensureLifecycleStatuses($organizationId);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $defaults = [
            ['Draft', 'draft', '#94A3B8', 1, 0],
            ['Assigned', 'assigned', '#0F766E', 2, 0],
            ['In Progress', 'in_progress', '#00AFC6', 3, 0],
            ['Submitted', 'submitted', '#7C3AED', 4, 0],
            ['Changes Requested', 'changes_requested', '#EA580C', 5, 0],
            ['Resubmitted', 'resubmitted', '#8B5CF6', 6, 0],
            ['Approved', 'approved', '#059669', 7, 1],
            ['Blocked', 'blocked', '#DC2626', 8, 0],
            ['Cancelled', 'cancelled', '#64748B', 9, 1],
            // Legacy-compatible statuses kept for existing records / boards.
            ['Backlog', 'backlog', '#64748B', 10, 0],
            ['To Do', 'todo', '#0F766E', 11, 0],
            ['Review', 'review', '#7C3AED', 12, 0],
            ['Completed', 'completed', '#059669', 13, 1],
        ];

        foreach ($defaults as $status) {
            Database::query(
                'INSERT INTO task_statuses (organization_id, name, slug, color, sort_order, is_closed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$organizationId, $status[0], $status[1], $status[2], $status[3], $status[4], $now, $now]
            );
        }
    }

    /** Add lifecycle statuses without removing custom org statuses. */
    public static function ensureLifecycleStatuses(int $organizationId): void
    {
        $now = date('Y-m-d H:i:s');
        $needed = [
            ['Draft', 'draft', '#94A3B8', 1, 0],
            ['Assigned', 'assigned', '#0F766E', 2, 0],
            ['In Progress', 'in_progress', '#00AFC6', 3, 0],
            ['Submitted', 'submitted', '#7C3AED', 4, 0],
            ['Changes Requested', 'changes_requested', '#EA580C', 5, 0],
            ['Resubmitted', 'resubmitted', '#8B5CF6', 6, 0],
            ['Approved', 'approved', '#059669', 7, 1],
            ['Blocked', 'blocked', '#DC2626', 8, 0],
            ['Cancelled', 'cancelled', '#64748B', 9, 1],
        ];
        foreach ($needed as $status) {
            if (self::findBySlug($organizationId, $status[1]) instanceof self) {
                continue;
            }
            Database::query(
                'INSERT INTO task_statuses (organization_id, name, slug, color, sort_order, is_closed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$organizationId, $status[0], $status[1], $status[2], $status[3], $status[4], $now, $now]
            );
        }
    }

    public static function slugExists(int $organizationId, string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM task_statuses WHERE organization_id = ? AND slug = ?';
        $params = [$organizationId, $slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return Database::query($sql . ' LIMIT 1', $params)->fetch() !== false;
    }

    public static function inUse(int $organizationId, string $slug): bool
    {
        return Database::query(
            'SELECT id FROM tasks WHERE organization_id = ? AND status = ? LIMIT 1',
            [$organizationId, $slug]
        )->fetch() !== false;
    }

    /** @param list<self> $statuses */
    public static function progressForSlug(array $statuses, string $slug): int
    {
        $total = count($statuses);
        if ($total === 0) {
            return 0;
        }

        foreach ($statuses as $index => $status) {
            if ($status->slug !== $slug) {
                continue;
            }
            if ((int) $status->is_closed === 1) {
                return 100;
            }
            if ($total === 1) {
                return 0;
            }

            return (int) round(($index / ($total - 1)) * 100);
        }

        return 0;
    }

    /** @param list<self> $statuses */
    public static function findInList(array $statuses, string $slug): ?self
    {
        foreach ($statuses as $status) {
            if ($status->slug === $slug) {
                return $status;
            }
        }

        return null;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO task_statuses (organization_id, name, slug, color, sort_order, is_closed, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['name'],
                $data['slug'],
                $data['color'],
                $data['sort_order'],
                $data['is_closed'],
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
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
            'UPDATE task_statuses SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query(
            'DELETE FROM task_statuses WHERE id = ? AND organization_id = ?',
            [$id, $organizationId]
        );
    }

    public static function countForOrganization(int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM task_statuses WHERE organization_id = ?',
            [$organizationId]
        )->fetchColumn();
    }
}
