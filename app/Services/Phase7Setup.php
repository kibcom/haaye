<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Phase7Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/007_phase7_finance.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 7 migration file is missing.');
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
        $permissions = [
            ['Finance', 'View finance', 'finance.view'],
            ['Finance', 'View invoices', 'invoices.view'],
            ['Finance', 'Manage invoices', 'invoices.manage'],
            ['Finance', 'Record payments', 'payments.manage'],
            ['Finance', 'View expenses', 'expenses.view'],
            ['Finance', 'View all expenses', 'expenses.view_all'],
            ['Finance', 'Create expenses', 'expenses.create'],
            ['Finance', 'Approve expenses', 'expenses.approve'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $row = Database::query('SELECT id FROM permissions WHERE slug = ? LIMIT 1', [$permission[2]])->fetch();
            if ($row) {
                $permissionIds[$permission[2]] = (int) $row['id'];
                continue;
            }
            Database::query(
                'INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)',
                [$permission[1], $permission[2], $permission[0], $now]
            );
            $permissionIds[$permission[2]] = (int) Database::lastInsertId();
        }

        $full = [
            'finance.view', 'invoices.view', 'invoices.manage', 'payments.manage',
            'expenses.view', 'expenses.view_all', 'expenses.create', 'expenses.approve',
        ];
        $delivery = ['finance.view', 'invoices.view', 'expenses.view', 'expenses.create'];
        $map = [
            'owner' => $full,
            'admin' => $full,
            'finance' => $full,
            'manager' => $delivery,
            'project_manager' => $delivery,
            'team_leader' => ['expenses.view', 'expenses.create'],
            'employee' => ['expenses.view', 'expenses.create'],
            'sales' => ['finance.view', 'invoices.view', 'expenses.view', 'expenses.view_all'],
        ];

        foreach ($map as $roleSlug => $slugs) {
            $role = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$roleSlug])->fetch();
            if (!$role) {
                continue;
            }
            foreach ($slugs as $permissionSlug) {
                AuthorizationService::grantPermissionToDefaultRole(
                    (int) $role['id'],
                    (int) ($permissionIds[$permissionSlug] ?? 0)
                );
            }
        }
    }

    public static function seedForOrganizations(): void
    {
        self::enrichDemo();
    }

    public static function enrichDemo(): void
    {
        $org = Database::query("SELECT id, invoice_next_number FROM organizations WHERE slug = 'abay-consulting' LIMIT 1")->fetch();
        if (!$org) {
            return;
        }

        $orgId = (int) $org['id'];
        $existing = Database::query(
            'SELECT id FROM invoices WHERE organization_id = ? AND number = ? LIMIT 1',
            [$orgId, 'INV-1001']
        )->fetch();
        if ($existing) {
            return;
        }

        $client = Database::query(
            "SELECT id FROM clients WHERE organization_id = ? AND name = 'Zemen Media' LIMIT 1",
            [$orgId]
        )->fetch();
        $project = Database::query(
            "SELECT id, client_id FROM projects WHERE organization_id = ? AND code = 'ZMN-BR' LIMIT 1",
            [$orgId]
        )->fetch();
        $finance = Database::query("SELECT id FROM users WHERE email = 'finance@haaye.com' LIMIT 1")->fetch();
        $priya = Database::query("SELECT id FROM users WHERE email = 'manager@haaye.com' LIMIT 1")->fetch();
        if (!$client || !$project || !$finance || !$priya) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Database::query(
            'INSERT INTO invoices (
                organization_id, client_id, project_id, number, issue_date, due_date, currency,
                subtotal, tax_rate, tax_amount, discount_amount, total, paid_amount, balance,
                status, notes, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orgId, (int) $client['id'], (int) $project['id'], 'INV-1001',
                '2026-09-01', '2026-09-20', 'ETB',
                '4000.00', '0.00', '0.00', '0.00', '4000.00', '1500.00', '2500.00',
                'partial', 'Discovery and concept work for the Zemen brand refresh.', (int) $finance['id'], $now, $now,
            ]
        );
        $invoiceId = (int) Database::lastInsertId();
        Database::query(
            'INSERT INTO invoice_items (organization_id, invoice_id, description, quantity, unit_price, line_total, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, 0)',
            [$orgId, $invoiceId, 'Zemen brand refresh discovery and concepts', '1.00', '4000.00', '4000.00']
        );
        Database::query(
            'INSERT INTO payments (
                organization_id, invoice_id, client_id, amount, paid_on, method, reference, notes, recorded_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orgId, $invoiceId, (int) $client['id'], '1500.00', '2026-09-15', 'bank', 'CBE-4401',
                'First installment.', (int) $finance['id'], $now,
            ]
        );
        Database::query(
            'INSERT INTO expenses (
                organization_id, user_id, project_id, client_id, category, amount, spent_on, description, status,
                decided_by, decided_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orgId, (int) $priya['id'], (int) $project['id'], (int) $client['id'], 'software', '180.00',
                '2026-09-18', 'Stock photo license for the Zemen brand refresh.', 'approved',
                (int) $finance['id'], $now, $now, $now,
            ]
        );

        if ((int) $org['invoice_next_number'] <= 1001) {
            Database::query('UPDATE organizations SET invoice_next_number = 1002 WHERE id = ?', [$orgId]);
        }
    }
}
