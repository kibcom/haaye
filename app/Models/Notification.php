<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Notification extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $user_id = 0;
    public string $title = '';
    public ?string $body = null;
    public ?string $link_path = null;
    public ?string $read_at = null;
    public ?string $created_at = null;

    public static function table(): string
    {
        return 'notifications';
    }

    public function isUnread(): bool
    {
        return $this->read_at === null || $this->read_at === '';
    }

    public function safePath(): ?string
    {
        $path = ltrim((string) $this->link_path, '/');
        $path = preg_replace('/#.*$/', '', $path) ?? $path;
        if (preg_match('#^(portal/(company|projects|invoices|payments|files|notifications)(/[0-9]+)?|deliverables/[0-9]+|approvals/[0-9]+|tasks/[0-9]+|invoices/[0-9]+|projects/[0-9]+|calendar|expenses/[0-9]+)$#', $path) !== 1) {
            return null;
        }

        return $path;
    }

    public static function findForUser(int $id, int $organizationId, int $userId): ?self
    {
        $row = Database::query(
            'SELECT * FROM notifications WHERE id = ? AND organization_id = ? AND user_id = ? LIMIT 1',
            [$id, $organizationId, $userId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forUser(int $organizationId, int $userId, int $limit = 50): array
    {
        $rows = Database::query(
            'SELECT * FROM notifications WHERE organization_id = ? AND user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit,
            [$organizationId, $userId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function unreadCount(int $organizationId, int $userId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM notifications WHERE organization_id = ? AND user_id = ? AND read_at IS NULL',
            [$organizationId, $userId]
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO notifications (organization_id, user_id, title, body, link_path, read_at, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)',
            [
                $data['organization_id'],
                $data['user_id'],
                $data['title'],
                $data['body'] ?? null,
                $data['link_path'] ?? null,
                $data['created_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function markRead(int $id, int $organizationId, int $userId): void
    {
        Database::query(
            'UPDATE notifications SET read_at = ? WHERE id = ? AND organization_id = ? AND user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $id, $organizationId, $userId]
        );
    }

    public static function markAllRead(int $organizationId, int $userId): void
    {
        Database::query(
            'UPDATE notifications SET read_at = ? WHERE organization_id = ? AND user_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $organizationId, $userId]
        );
    }
}
