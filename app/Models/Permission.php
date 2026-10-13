<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Permission extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $slug = '';
    public string $group_name = '';
    public ?string $created_at = null;

    public static function table(): string
    {
        return 'permissions';
    }

    public static function groupedForRole(int $roleId): array
    {
        $rows = Database::query(
            'SELECT p.slug, p.name, p.group_name
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?
             ORDER BY p.group_name ASC, p.name ASC',
            [$roleId]
        )->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group_name']][] = $row;
        }

        return $grouped;
    }

    /** @return array<string, list<array{id:int,slug:string,name:string}>> */
    public static function groupedAssignable(): array
    {
        $rows = Database::query(
            "SELECT id, slug, name, group_name
             FROM permissions
             WHERE slug NOT LIKE 'platform.%'
             ORDER BY group_name ASC, name ASC"
        )->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group_name']][] = [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
            ];
        }

        return $grouped;
    }
}
