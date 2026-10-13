<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TaskComment extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $task_id = 0;
    public ?int $user_id = null;
    public string $body = '';
    public ?string $created_at = null;
    public ?string $user_name = null;

    public static function table(): string
    {
        return 'task_comments';
    }

    /** @return list<self> */
    public static function forTask(int $taskId, int $organizationId): array
    {
        $rows = Database::query(
            'SELECT c.*, u.name AS user_name
             FROM task_comments c
             LEFT JOIN users u ON u.id = c.user_id AND u.organization_id = c.organization_id
             WHERE c.task_id = ? AND c.organization_id = ?
             ORDER BY c.created_at ASC, c.id ASC',
            [$taskId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInTask(int $id, int $taskId, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT c.*, u.name AS user_name
             FROM task_comments c
             LEFT JOIN users u ON u.id = c.user_id AND u.organization_id = c.organization_id
             WHERE c.id = ? AND c.task_id = ? AND c.organization_id = ?
             LIMIT 1',
            [$id, $taskId, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO task_comments (organization_id, task_id, user_id, body, created_at) VALUES (?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['task_id'],
                $data['user_id'] ?? null,
                $data['body'],
                $data['created_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function deleteInTask(int $id, int $taskId, int $organizationId): void
    {
        Database::query(
            'DELETE FROM task_comments WHERE id = ? AND task_id = ? AND organization_id = ?',
            [$id, $taskId, $organizationId]
        );
    }
}
