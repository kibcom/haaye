<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ContentGrid extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $client_id = 0;
    public string $start_date = '';
    public string $end_date = '';
    public ?string $notes = null;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $client_name = null;
    public ?string $creator_name = null;
    public ?int $edited_count = null;
    public ?int $posted_count = null;
    public ?int $day_count = null;

    public static function table(): string
    {
        return 'content_grids';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE g.id = ? AND g.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /**
     * @return list<self>
     */
    public static function listFor(int $organizationId, ?int $clientId = null): array
    {
        $where = ['g.organization_id = ?'];
        $params = [$organizationId];
        if ($clientId !== null && $clientId > 0) {
            $where[] = 'g.client_id = ?';
            $params[] = $clientId;
        }

        $rows = Database::query(
            self::selectSql()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY g.start_date DESC, g.id DESC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO content_grids (
                organization_id, client_id, start_date, end_date, notes, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['client_id'],
                $data['start_date'],
                $data['end_date'],
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = ['client_id', 'start_date', 'end_date', 'notes', 'updated_at'];
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
            'UPDATE content_grids SET ' . implode(', ', $sets) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query(
            'DELETE FROM content_grids WHERE id = ? AND organization_id = ?',
            [$id, $organizationId]
        );
    }

    private static function selectSql(): string
    {
        return 'SELECT g.*,
                    c.name AS client_name,
                    u.name AS creator_name,
                    (SELECT COUNT(*) FROM content_grid_days d WHERE d.content_grid_id = g.id) AS day_count,
                    (SELECT COUNT(*) FROM content_grid_days d WHERE d.content_grid_id = g.id AND d.is_edited = 1) AS edited_count,
                    (SELECT COUNT(*) FROM content_grid_days d WHERE d.content_grid_id = g.id AND d.is_posted = 1) AS posted_count
                FROM content_grids g
                INNER JOIN clients c ON c.id = g.client_id AND c.organization_id = g.organization_id
                LEFT JOIN users u ON u.id = g.created_by';
    }
}
