<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class InventoryItem extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $name = '';
    public string $ownership = 'owned';
    public ?string $vendor_name = null;
    public int $is_active = 1;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function table(): string
    {
        return 'inventory_items';
    }

    /**
     * @return list<self>
     */
    public static function listFor(int $organizationId, ?string $ownership = null, bool $activeOnly = true): array
    {
        $where = ['organization_id = ?'];
        $params = [$organizationId];
        if ($ownership !== null && $ownership !== '') {
            $where[] = 'ownership = ?';
            $params[] = $ownership;
        }
        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        $rows = Database::query(
            'SELECT * FROM inventory_items WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM inventory_items WHERE id = ? AND organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO inventory_items (organization_id, name, ownership, vendor_name, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['name'],
                $data['ownership'],
                $data['vendor_name'] ?? null,
                $data['is_active'] ?? 1,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }
}
