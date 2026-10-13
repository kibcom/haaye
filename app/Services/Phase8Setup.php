<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Phase8Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/008_phase8_portal.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 8 migration file is missing.');
        }

        $pdo = Database::connection();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $row = Database::query("SELECT id FROM permissions WHERE slug = 'portal.view' LIMIT 1")->fetch();
        if ($row) {
            $permissionId = (int) $row['id'];
        } else {
            Database::query(
                'INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)',
                ['Use the client portal', 'portal.view', 'Portal', $now]
            );
            $permissionId = (int) Database::lastInsertId();
        }

        $role = Database::query("SELECT id FROM roles WHERE slug = 'client' LIMIT 1")->fetch();
        if (!$role) {
            return;
        }
        AuthorizationService::grantPermissionToDefaultRole((int) $role['id'], $permissionId);
    }

    public static function seedForOrganizations(): void
    {
        self::enrichDemo();
    }

    public static function enrichDemo(): void
    {
        $user = Database::query(
            "SELECT u.id, u.organization_id, u.client_id
             FROM users u
             WHERE u.email = 'client@haaye.com' LIMIT 1"
        )->fetch();
        if (!$user || $user['client_id'] === null) {
            return;
        }

        $userId = (int) $user['id'];
        $orgId = (int) $user['organization_id'];
        $existing = (int) Database::query(
            'SELECT COUNT(*) FROM notifications WHERE organization_id = ? AND user_id = ?',
            [$orgId, $userId]
        )->fetchColumn();
        if ($existing > 0) {
            return;
        }

        $invoice = Database::query(
            "SELECT id, number FROM invoices WHERE organization_id = ? AND client_id = ? AND number = 'INV-1001' LIMIT 1",
            [$orgId, (int) $user['client_id']]
        )->fetch();
        $now = date('Y-m-d H:i:s');
        $rows = [];
        if ($invoice) {
            $rows[] = [
                'Invoice ' . $invoice['number'] . ' is ready',
                'Review the balance and due date in your portal.',
                'portal/invoices/' . (int) $invoice['id'],
            ];
            $rows[] = [
                'A payment was recorded',
                'ETB 1,500.00 was applied to ' . $invoice['number'] . '.',
                'portal/invoices/' . (int) $invoice['id'],
            ];
        }

        $deliverable = Database::query(
            "SELECT id, name FROM deliverables WHERE organization_id = ? AND client_id = ? AND name = 'Homepage hero' LIMIT 1",
            [$orgId, (int) $user['client_id']]
        )->fetch();
        if ($deliverable) {
            $rows[] = [
                $deliverable['name'] . ' was approved',
                'The deliverable is available in your portal.',
                'deliverables/' . (int) $deliverable['id'],
            ];
        }

        foreach ($rows as $row) {
            Database::query(
                'INSERT INTO notifications (organization_id, user_id, title, body, link_path, read_at, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)',
                [$orgId, $userId, $row[0], $row[1], $row[2], $now]
            );
        }
    }
}
