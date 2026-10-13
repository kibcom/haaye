<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\User;

final class Phase3Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/003_phase3_projects.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 3 migration file is missing.');
        }

        $pdo = Database::connection();
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        $fk = Database::query(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND CONSTRAINT_NAME = 'fk_projects_manager'"
        )->fetch();

        if (!$fk) {
            $pdo->exec(
                'ALTER TABLE projects ADD CONSTRAINT fk_projects_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL'
            );
        }
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Projects', 'View assigned projects', 'projects.view'],
            ['Projects', 'View all projects', 'projects.view_all'],
            ['Projects', 'Create projects', 'projects.create'],
            ['Projects', 'Update projects', 'projects.update'],
            ['Projects', 'Manage project members', 'projects.members.manage'],
            ['Projects', 'Manage milestones', 'projects.milestones.manage'],
            ['Projects', 'Manage project statuses', 'projects.statuses.manage'],
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

        $map = [
            'owner' => ['projects.view', 'projects.view_all', 'projects.create', 'projects.update', 'projects.members.manage', 'projects.milestones.manage', 'projects.statuses.manage'],
            'admin' => ['projects.view', 'projects.view_all', 'projects.create', 'projects.update', 'projects.members.manage', 'projects.milestones.manage', 'projects.statuses.manage'],
            'manager' => ['projects.view', 'projects.create', 'projects.update', 'projects.members.manage', 'projects.milestones.manage'],
            'project_manager' => ['projects.view', 'projects.create', 'projects.update', 'projects.members.manage', 'projects.milestones.manage'],
            'team_leader' => ['projects.view'],
            'employee' => ['projects.view'],
            'finance' => ['projects.view', 'projects.view_all'],
            'sales' => ['projects.view', 'projects.view_all'],
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
            ProjectStatus::ensureDefaults((int) $row['id']);
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
        ProjectStatus::ensureDefaults($orgId);

        $manager = User::findByEmail('manager@haaye.com');
        $employee = User::findByEmail('employee@haaye.com');
        $now = date('Y-m-d H:i:s');

        $helios = Database::query(
            'SELECT id FROM projects WHERE organization_id = ? AND code = ? LIMIT 1',
            [$orgId, 'ZMN-BR']
        )->fetch();

        if ($helios && $manager instanceof User) {
            $projectId = (int) $helios['id'];
            Project::updateById($projectId, $orgId, [
                'budget' => '24000.00',
                'billing_type' => 'fixed',
                'manager_user_id' => $manager->id,
                'progress' => 35,
                'payment_status' => 'partial',
                'description' => 'Brand system, campaign pages, and launch support for Zemen Media.',
                'updated_at' => $now,
            ]);
            ProjectMember::upsert($orgId, $projectId, (int) $manager->id, 'manager');
            if ($employee instanceof User) {
                ProjectMember::upsert($orgId, $projectId, (int) $employee->id, 'member');
            }

            if (Milestone::countForProject($projectId, $orgId) === 0) {
                Milestone::create([
                    'organization_id' => $orgId,
                    'project_id' => $projectId,
                    'name' => 'Discovery',
                    'description' => 'Workshops and brand audit.',
                    'due_date' => '2026-10-15',
                    'status' => 'completed',
                    'progress' => 100,
                    'owner_user_id' => $manager->id,
                    'completed_at' => '2026-10-12 16:00:00',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Milestone::create([
                    'organization_id' => $orgId,
                    'project_id' => $projectId,
                    'name' => 'Creative concepts',
                    'description' => 'Two directions for client review.',
                    'due_date' => '2026-11-05',
                    'status' => 'in_progress',
                    'progress' => 40,
                    'owner_user_id' => $employee?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Milestone::create([
                    'organization_id' => $orgId,
                    'project_id' => $projectId,
                    'name' => 'Launch',
                    'description' => 'Final assets and handoff.',
                    'due_date' => '2026-12-15',
                    'status' => 'open',
                    'progress' => 0,
                    'owner_user_id' => $manager->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $brightline = Database::query(
            'SELECT id FROM projects WHERE organization_id = ? AND code = ? LIMIT 1',
            [$orgId, 'BSH-ONB']
        )->fetch();

        if ($brightline && $manager instanceof User) {
            $projectId = (int) $brightline['id'];
            Project::updateById($projectId, $orgId, [
                'budget' => '8000.00',
                'billing_type' => 'hourly',
                'manager_user_id' => $manager->id,
                'progress' => 10,
                'payment_status' => 'unpaid',
                'description' => 'Document pack for the Bishoftu site. Paused with the client.',
                'updated_at' => $now,
            ]);
            ProjectMember::upsert($orgId, $projectId, (int) $manager->id, 'manager');
        }
    }
}
