<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Approval;
use App\Models\Deliverable;
use App\Models\DeliverableStatus;
use App\Models\User;
use App\Models\WorkflowTransition;

final class Phase5Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/005_phase5_workflows.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 5 migration file is missing.');
        }

        $pdo = Database::connection();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        $fk = Database::query(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND CONSTRAINT_NAME = 'fk_users_client'"
        )->fetch();
        if (!$fk) {
            $pdo->exec('ALTER TABLE users ADD CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL');
        }
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Workflows', 'View workflows', 'workflows.view'],
            ['Workflows', 'Manage workflows', 'workflows.manage'],
            ['Approvals', 'View approvals', 'approvals.view'],
            ['Approvals', 'Request approvals', 'approvals.request'],
            ['Approvals', 'Decide internal approvals', 'approvals.decide'],
            ['Approvals', 'Decide client approvals', 'approvals.client'],
            ['Deliverables', 'View deliverables', 'deliverables.view'],
            ['Deliverables', 'Create deliverables', 'deliverables.create'],
            ['Deliverables', 'Update deliverables', 'deliverables.update'],
            ['Deliverables', 'Manage deliverable statuses', 'deliverables.statuses.manage'],
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

        $owner = [
            'workflows.view', 'workflows.manage', 'approvals.view', 'approvals.request', 'approvals.decide',
            'deliverables.view', 'deliverables.create', 'deliverables.update', 'deliverables.statuses.manage',
        ];
        $delivery = [
            'workflows.view', 'approvals.view', 'approvals.request', 'approvals.decide',
            'deliverables.view', 'deliverables.create', 'deliverables.update',
        ];
        $map = [
            'owner' => $owner,
            'admin' => $owner,
            'manager' => $delivery,
            'project_manager' => $delivery,
            'team_leader' => ['deliverables.view', 'deliverables.update', 'approvals.view', 'approvals.request'],
            'employee' => ['deliverables.view', 'approvals.view', 'approvals.request'],
            'finance' => ['deliverables.view', 'approvals.view', 'workflows.view'],
            'sales' => ['deliverables.view', 'approvals.view', 'workflows.view'],
            'client' => ['deliverables.view', 'approvals.view', 'approvals.client'],
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
        $rows = Database::query('SELECT id FROM organizations')->fetchAll();
        foreach ($rows as $row) {
            DeliverableStatus::ensureDefaults((int) $row['id']);
            WorkflowTransition::ensureDefaults((int) $row['id']);
        }
        self::enrichDemo();
    }

    public static function enrichDemo(): void
    {
        $org = Database::query("SELECT id FROM organizations WHERE slug = 'abay-consulting' LIMIT 1")->fetch();
        if (!$org) {
            return;
        }

        $orgId = (int) $org['id'];
        DeliverableStatus::ensureDefaults($orgId);

        $helios = Database::query('SELECT id FROM clients WHERE organization_id = ? AND name = ? LIMIT 1', [$orgId, 'Zemen Media'])->fetch();
        $clientUser = User::findByEmail('client@haaye.com');
        if ($helios && $clientUser instanceof User && (int) $clientUser->organization_id === $orgId && $clientUser->client_id === null) {
            Database::query('UPDATE users SET client_id = ? WHERE id = ? AND organization_id = ?', [(int) $helios['id'], $clientUser->id, $orgId]);
        }

        WorkflowTransition::ensureDefaults($orgId);

        $project = Database::query(
            'SELECT id, client_id FROM projects WHERE organization_id = ? AND code = ? LIMIT 1',
            [$orgId, 'ZMN-BR']
        )->fetch();
        if (!$project) {
            return;
        }

        $existing = Database::query(
            'SELECT id FROM deliverables WHERE organization_id = ? AND project_id = ? AND name = ? LIMIT 1',
            [$orgId, (int) $project['id'], 'Homepage hero']
        )->fetch();
        if ($existing) {
            return;
        }

        $manager = User::findByEmail('manager@haaye.com');
        $employee = User::findByEmail('employee@haaye.com');
        $now = date('Y-m-d H:i:s');
        $id = Deliverable::create([
            'organization_id' => $orgId,
            'project_id' => (int) $project['id'],
            'client_id' => (int) $project['client_id'],
            'name' => 'Homepage hero',
            'description' => 'Final hero section for the brand refresh homepage.',
            'due_date' => '2026-10-22',
            'assignee_user_id' => $employee instanceof User ? $employee->id : null,
            'status' => 'client_review',
            'version' => 1,
            'client_approval_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Approval::create([
            'organization_id' => $orgId,
            'project_id' => (int) $project['id'],
            'client_id' => (int) $project['client_id'],
            'entity_type' => 'deliverable',
            'entity_id' => $id,
            'entity_label' => 'Homepage hero',
            'approval_kind' => 'client',
            'from_status' => 'client_review',
            'to_status' => 'approved',
            'requested_by' => $manager instanceof User ? $manager->id : null,
            'created_at' => $now,
        ]);
    }
}
