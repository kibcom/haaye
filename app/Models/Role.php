<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Role extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $slug = '';
    public ?string $description = null;
    public string $scope = 'organization';
    public int $is_system = 1;
    public int $access_customized = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function table(): string
    {
        return 'roles';
    }

    public static function findBySlug(string $slug): ?self
    {
        $row = Database::query('SELECT * FROM roles WHERE slug = ? LIMIT 1', [$slug])->fetch();
        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function organizationRoles(): array
    {
        $rows = Database::query(
            "SELECT * FROM roles WHERE scope = 'organization' ORDER BY id ASC"
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function allRoles(): array
    {
        $rows = Database::query('SELECT * FROM roles ORDER BY scope ASC, id ASC')->fetchAll();
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function assignableFor(string $actorSlug): array
    {
        $roles = self::organizationRoles();

        if ($actorSlug === 'owner') {
            return $roles;
        }

        return array_values(array_filter($roles, static function (self $role): bool {
            return $role->slug !== 'owner';
        }));
    }
}
