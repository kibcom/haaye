<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class DeliverableStatus extends Model
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
        return 'deliverable_statuses';
    }

    /** @return list<self> */
    public static function forOrganization(int $organizationId): array
    {
        $rows = Database::query(
            'SELECT * FROM deliverable_statuses WHERE organization_id = ? ORDER BY sort_order ASC, id ASC',
            [$organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM deliverable_statuses WHERE id = ? AND organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function findBySlug(int $organizationId, string $slug): ?self
    {
        $row = Database::query(
            'SELECT * FROM deliverable_statuses WHERE organization_id = ? AND slug = ? LIMIT 1',
            [$organizationId, $slug]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function ensureDefaults(int $organizationId): void
    {
        $count = (int) Database::query(
            'SELECT COUNT(*) FROM deliverable_statuses WHERE organization_id = ?',
            [$organizationId]
        )->fetchColumn();

        if ($count > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $defaults = [
            ['Draft', 'draft', '#64748B', 1, 0],
            ['Internal Review', 'internal_review', '#0F766E', 2, 0],
            ['Client Review', 'client_review', '#00AFC6', 3, 0],
            ['Changes Requested', 'changes_requested', '#D97706', 4, 0],
            ['Approved', 'approved', '#7C3AED', 5, 0],
            ['Delivered', 'delivered', '#059669', 6, 1],
        ];

        foreach ($defaults as $status) {
            Database::query(
                'INSERT INTO deliverable_statuses (organization_id, name, slug, color, sort_order, is_closed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$organizationId, $status[0], $status[1], $status[2], $status[3], $status[4], $now, $now]
            );
        }
    }

    public static function slugExists(int $organizationId, string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM deliverable_statuses WHERE organization_id = ? AND slug = ?';
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
            'SELECT id FROM deliverables WHERE organization_id = ? AND status = ? LIMIT 1',
            [$organizationId, $slug]
        )->fetch() !== false;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO deliverable_statuses (organization_id, name, slug, color, sort_order, is_closed, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'], $data['name'], $data['slug'], $data['color'],
                $data['sort_order'], $data['is_closed'], $data['created_at'], $data['updated_at'],
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
            'UPDATE deliverable_statuses SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM deliverable_statuses WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    public static function countForOrganization(int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM deliverable_statuses WHERE organization_id = ?',
            [$organizationId]
        )->fetchColumn();
    }
}
