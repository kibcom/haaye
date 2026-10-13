<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TaskAttachment extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $task_id = 0;
    public ?int $comment_id = null;
    public ?int $user_id = null;
    public string $original_name = '';
    public string $stored_path = '';
    public string $mime_type = '';
    public int $size_bytes = 0;
    public ?string $created_at = null;
    public ?string $user_name = null;

    public static function table(): string
    {
        return 'task_attachments';
    }

    /** @return list<self> */
    public static function forTask(int $taskId, int $organizationId): array
    {
        $rows = Database::query(
            'SELECT a.*, u.name AS user_name
             FROM task_attachments a
             LEFT JOIN users u ON u.id = a.user_id AND u.organization_id = a.organization_id
             WHERE a.task_id = ? AND a.organization_id = ?
             ORDER BY a.created_at DESC, a.id DESC',
            [$taskId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInTask(int $id, int $taskId, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT a.*, u.name AS user_name
             FROM task_attachments a
             LEFT JOIN users u ON u.id = a.user_id AND u.organization_id = a.organization_id
             WHERE a.id = ? AND a.task_id = ? AND a.organization_id = ?
             LIMIT 1',
            [$id, $taskId, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO task_attachments (
                organization_id, task_id, comment_id, user_id, original_name, stored_path, mime_type, size_bytes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['task_id'],
                $data['comment_id'] ?? null,
                $data['user_id'] ?? null,
                $data['original_name'],
                $data['stored_path'],
                $data['mime_type'],
                $data['size_bytes'],
                $data['created_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function deleteInTask(int $id, int $taskId, int $organizationId): void
    {
        Database::query(
            'DELETE FROM task_attachments WHERE id = ? AND task_id = ? AND organization_id = ?',
            [$id, $taskId, $organizationId]
        );
    }
}
