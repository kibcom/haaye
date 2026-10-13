<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;

final class AuthorizationService
{
    public static function applySchema(): void
    {
        Database::query(
            'ALTER TABLE users ADD COLUMN IF NOT EXISTS custom_permissions TINYINT(1) NOT NULL DEFAULT 0'
        );
        Database::query(
            'ALTER TABLE roles ADD COLUMN IF NOT EXISTS access_customized TINYINT(1) NOT NULL DEFAULT 0'
        );
        Database::query(
            'CREATE TABLE IF NOT EXISTS user_permissions (
                user_id BIGINT UNSIGNED NOT NULL,
                permission_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (user_id, permission_id),
                KEY idx_user_permissions_permission (permission_id),
                CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $now = date('Y-m-d H:i:s');
        $exists = Database::query("SELECT id FROM permissions WHERE slug = 'roles.update' LIMIT 1")->fetch();
        if (!$exists) {
            Database::query(
                'INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)',
                ['Update role access', 'roles.update', 'Roles', $now]
            );
            $permissionId = (int) Database::lastInsertId();
        } else {
            $permissionId = (int) $exists['id'];
        }

        foreach (['owner', 'admin'] as $slug) {
            $role = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])->fetch();
            if (!$role) {
                continue;
            }
            $linked = Database::query(
                'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1',
                [(int) $role['id'], $permissionId]
            )->fetch();
            if ($linked) {
                continue;
            }
            Database::query(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [(int) $role['id'], $permissionId]
            );
        }
    }

    public static function isRoleAccessCustomized(int $roleId): bool
    {
        $row = Database::query(
            'SELECT access_customized FROM roles WHERE id = ? LIMIT 1',
            [$roleId]
        )->fetch();

        return $row !== false && (int) ($row['access_customized'] ?? 0) === 1;
    }

    public static function markRoleAccessCustomized(int $roleId): void
    {
        Database::query(
            'UPDATE roles SET access_customized = 1, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $roleId]
        );
    }

    /**
     * Grant a permission to a role unless an admin already customized that role's access.
     * Keeps /roles checkbox changes from being overwritten by phase seeders.
     */
    public static function grantPermissionToDefaultRole(int $roleId, int $permissionId): void
    {
        if ($roleId < 1 || $permissionId < 1) {
            return;
        }
        if (self::isRoleAccessCustomized($roleId)) {
            return;
        }

        $exists = Database::query(
            'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1',
            [$roleId, $permissionId]
        )->fetch();
        if ($exists) {
            return;
        }

        Database::query(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$roleId, $permissionId]
        );
    }

    /** @return list<string> */
    public static function permissionsForRole(int $roleId): array
    {
        $rows = Database::query(
            'SELECT p.slug
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?',
            [$roleId]
        )->fetchAll();

        return array_values(array_map(static fn (array $row): string => (string) $row['slug'], $rows));
    }

    /** @return list<string> */
    public static function permissionsForUser(User $user): array
    {
        if ((int) ($user->custom_permissions ?? 0) === 1) {
            $rows = Database::query(
                'SELECT p.slug
                 FROM permissions p
                 INNER JOIN user_permissions up ON up.permission_id = p.id
                 WHERE up.user_id = ?',
                [(int) $user->id]
            )->fetchAll();

            return array_values(array_map(static fn (array $row): string => (string) $row['slug'], $rows));
        }

        return self::permissionsForRole((int) $user->role_id);
    }

    /** @return list<int> */
    public static function permissionIdsForRole(int $roleId): array
    {
        $rows = Database::query(
            'SELECT permission_id FROM role_permissions WHERE role_id = ?',
            [$roleId]
        )->fetchAll();

        return array_values(array_map(static fn (array $row): int => (int) $row['permission_id'], $rows));
    }

    /** @return list<int> */
    public static function permissionIdsForUser(int $userId): array
    {
        $rows = Database::query(
            'SELECT permission_id FROM user_permissions WHERE user_id = ?',
            [$userId]
        )->fetchAll();

        return array_values(array_map(static fn (array $row): int => (int) $row['permission_id'], $rows));
    }

    /** @param list<int> $permissionIds */
    public static function syncUserPermissions(int $userId, bool $custom, array $permissionIds): void
    {
        Database::query('DELETE FROM user_permissions WHERE user_id = ?', [$userId]);
        Database::query(
            'UPDATE users SET custom_permissions = ?, updated_at = ? WHERE id = ?',
            [$custom ? 1 : 0, date('Y-m-d H:i:s'), $userId]
        );

        if (!$custom) {
            return;
        }

        $permissionIds = array_values(array_unique(array_filter($permissionIds, static function ($id): bool {
            return is_int($id) || (is_string($id) && ctype_digit($id));
        })));

        foreach ($permissionIds as $permissionId) {
            $id = (int) $permissionId;
            if ($id <= 0 || !self::isAssignablePermission($id)) {
                continue;
            }
            Database::query(
                'INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)',
                [$userId, $id]
            );
        }
    }

    /** @param list<int> $permissionIds */
    public static function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        Database::query('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
        $permissionIds = array_values(array_unique(array_filter($permissionIds, static function ($id): bool {
            return is_int($id) || (is_string($id) && ctype_digit($id));
        })));

        foreach ($permissionIds as $permissionId) {
            $id = (int) $permissionId;
            if ($id <= 0 || !self::isAssignablePermission($id)) {
                continue;
            }
            Database::query(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, $id]
            );
        }

        // Admin chose exactly these boxes — do not let phase seeders re-add defaults.
        self::markRoleAccessCustomized($roleId);
    }

    public static function isAssignablePermission(int $permissionId): bool
    {
        $row = Database::query(
            "SELECT id FROM permissions WHERE id = ? AND slug NOT LIKE 'platform.%' LIMIT 1",
            [$permissionId]
        )->fetch();

        return $row !== false;
    }
}
