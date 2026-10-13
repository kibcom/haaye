<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Phase13Setup
{
    /** @var list<string> */
    public const DEFAULT_OWNED = [
        'Camera', 'Tripod', 'Changed Batteries', 'Memory Cards', 'Extra Memory Card', 'Gimbal',
        'Stands', 'Reflector', 'Batteries', 'Lights', 'Vest/Uniform', 'Lens Filters',
        'Soft Box', 'Laptop', 'Roll Cable', 'All Chargers', 'Adapters', 'Microphone',
    ];

    public static function applySchema(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS inventory_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                ownership ENUM(\'owned\', \'rental\') NOT NULL DEFAULT \'owned\',
                vendor_name VARCHAR(160) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_inventory_org (organization_id),
                INDEX idx_inventory_ownership (ownership),
                CONSTRAINT fk_inventory_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::ensureBookingColumn('checklist_status', "VARCHAR(40) NOT NULL DEFAULT 'draft' AFTER represented_signed");
        self::ensureBookingColumn('submitted_to_user_id', 'BIGINT UNSIGNED NULL AFTER checklist_status');
        self::ensureBookingColumn('submitted_at', 'DATETIME NULL AFTER submitted_to_user_id');
        self::ensureBookingColumn('reviewed_by', 'BIGINT UNSIGNED NULL AFTER submitted_at');
        self::ensureBookingColumn('reviewed_at', 'DATETIME NULL AFTER reviewed_by');
        self::ensureBookingColumn('review_note', 'VARCHAR(255) NULL AFTER reviewed_at');
        self::ensureBookingColumn('selected_equipment_json', 'LONGTEXT NULL AFTER review_note');

        self::seedDefaultInventory();
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Inventory', 'View inventory', 'inventory.view'],
            ['Inventory', 'Manage inventory items', 'inventory.manage'],
            ['Bookings', 'Submit booking checklist for approval', 'bookings.submit'],
            ['Bookings', 'Approve booking checklist', 'bookings.approve'],
        ];
        $permissionIds = [];
        foreach ($permissions as $permission) {
            $row = Database::query('SELECT id FROM permissions WHERE slug = ? LIMIT 1', [$permission[2]])->fetch();
            if ($row) {
                Database::query(
                    'UPDATE permissions SET name = ?, group_name = ? WHERE id = ?',
                    [$permission[1], $permission[0], (int) $row['id']]
                );
                $permissionIds[$permission[2]] = (int) $row['id'];
                continue;
            }
            Database::query(
                'INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)',
                [$permission[1], $permission[2], $permission[0], $now]
            );
            $permissionIds[$permission[2]] = (int) Database::lastInsertId();
        }

        $map = [
            'owner' => ['inventory.view', 'inventory.manage', 'bookings.submit', 'bookings.approve'],
            'admin' => ['inventory.view', 'inventory.manage', 'bookings.submit', 'bookings.approve'],
            'general_manager' => ['inventory.view', 'inventory.manage', 'bookings.view', 'bookings.update', 'bookings.submit', 'bookings.approve'],
            'operations_manager' => ['inventory.view', 'inventory.manage', 'bookings.view', 'bookings.create', 'bookings.update', 'bookings.submit', 'bookings.approve'],
            'manager' => ['inventory.view', 'inventory.manage', 'bookings.submit', 'bookings.approve'],
            'project_manager' => ['inventory.view', 'bookings.submit'],
            'videographer' => ['inventory.view', 'bookings.view', 'bookings.create', 'bookings.submit'],
            'editor' => ['inventory.view', 'bookings.view'],
            'social_media_manager' => ['inventory.view', 'bookings.view', 'bookings.create', 'bookings.submit'],
            'site_locator' => ['inventory.view', 'bookings.view', 'bookings.create', 'bookings.submit'],
            'sales' => ['inventory.view', 'bookings.view', 'bookings.create', 'bookings.submit'],
            'employee' => ['inventory.view', 'bookings.submit'],
            'team_leader' => ['inventory.view', 'bookings.submit'],
        ];

        foreach ($map as $slug => $slugs) {
            $role = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])->fetch();
            if (!$role) {
                continue;
            }
            $roleId = (int) $role['id'];
            foreach ($slugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug]
                    ?? (int) (Database::query('SELECT id FROM permissions WHERE slug = ? LIMIT 1', [$permissionSlug])->fetch()['id'] ?? 0);
                AuthorizationService::grantPermissionToDefaultRole($roleId, $permissionId);
            }
        }
    }

    private static function seedDefaultInventory(): void
    {
        $orgs = Database::query('SELECT id FROM organizations')->fetchAll();
        $now = date('Y-m-d H:i:s');
        foreach ($orgs as $org) {
            $orgId = (int) $org['id'];
            $count = (int) (Database::query(
                'SELECT COUNT(*) AS c FROM inventory_items WHERE organization_id = ?',
                [$orgId]
            )->fetch()['c'] ?? 0);
            if ($count > 0) {
                continue;
            }
            foreach (self::DEFAULT_OWNED as $name) {
                Database::query(
                    'INSERT INTO inventory_items (organization_id, name, ownership, vendor_name, is_active, created_at, updated_at)
                     VALUES (?, ?, \'owned\', NULL, 1, ?, ?)',
                    [$orgId, $name, $now, $now]
                );
            }
        }
    }

    private static function ensureBookingColumn(string $name, string $definition): void
    {
        $row = Database::query(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            ['bookings', $name]
        )->fetch();
        if ($row) {
            return;
        }
        Database::connection()->exec('ALTER TABLE bookings ADD COLUMN ' . $name . ' ' . $definition);
    }
}
