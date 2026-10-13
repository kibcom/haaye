<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Role;
use App\Models\User;

final class Phase10Setup
{
    /** @var list<array{0:string,1:string,2:string}> */
    public const ORG_ROLES = [
        ['General Manager', 'general_manager', 'Full workflow access: roles, tasks, content grids, and approvals'],
        ['Operations Manager', 'operations_manager', 'Runs workflow roles, tasks, content grids, and approvals'],
        ['Social Media Manager', 'social_media_manager', 'Workflow tasks plus monthly content grids per company'],
        ['Editor', 'editor', 'Works assigned workflow tasks and deliverables'],
        ['Videographer', 'videographer', 'Works assigned workflow capture tasks'],
        ['Site Locator', 'site_locator', 'Works location tasks in the production workflow'],
    ];

    /** @var list<string> */
    public const DEFAULT_PIPELINE_ROLES = [
        'Sales',
        'Site Locator',
        'Videographer',
        'Editor',
        'Social Media Manager',
        'Operations Manager',
        'General Manager',
    ];

    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/010_work_flow.sql');
        if ($sql === false) {
            throw new \RuntimeException('Workflow migration file is missing.');
        }

        $pdo = Database::connection();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedRoles(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (self::ORG_ROLES as $role) {
            $exists = Database::query(
                'SELECT id FROM roles WHERE slug = ? LIMIT 1',
                [$role[1]]
            )->fetch();
            if ($exists) {
                Database::query(
                    'UPDATE roles SET name = ?, description = ?, updated_at = ? WHERE id = ?',
                    [$role[0], $role[2], $now, (int) $exists['id']]
                );
                continue;
            }

            Database::query(
                'INSERT INTO roles (name, slug, description, scope, is_system, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?)',
                [$role[0], $role[1], $role[2], 'organization', $now, $now]
            );
        }

        // Keep Sales description clear for the production team.
        Database::query(
            "UPDATE roles SET name = 'Sales', description = 'Wins clients and starts workflow tasks on projects', updated_at = ?
             WHERE slug = 'sales'",
            [$now]
        );
    }

    public static function seedPermissions(): void
    {
        self::seedRoles();

        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Workflow', 'View workflow hub and shared tasks', 'work_flow.view'],
            ['Workflow', 'Manage workflow roles, tasks, and delete content grids', 'work_flow.manage'],
            ['Workflow', 'Assign workflow tasks and view/edit monthly content grids', 'work_flow.assign'],
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
            'owner' => ['work_flow.view', 'work_flow.manage', 'work_flow.assign', 'projects.view_all', 'approvals.view', 'approvals.decide'],
            'admin' => ['work_flow.view', 'work_flow.manage', 'work_flow.assign', 'projects.view_all', 'approvals.view', 'approvals.decide'],
            'general_manager' => [
                'dashboard.view', 'organization.view', 'profile.view', 'profile.update',
                'users.view', 'roles.view',
                'clients.view', 'leads.view', 'projects.view', 'projects.view_all', 'projects.update',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
                'approvals.view', 'approvals.decide', 'approvals.request',
                'work_flow.view', 'work_flow.manage', 'work_flow.assign',
                'activity.view', 'calendar.view',
            ],
            'operations_manager' => [
                'dashboard.view', 'organization.view', 'profile.view', 'profile.update',
                'users.view', 'roles.view',
                'clients.view', 'leads.view', 'projects.view', 'projects.view_all', 'projects.update',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
                'approvals.view', 'approvals.decide', 'approvals.request',
                'work_flow.view', 'work_flow.manage', 'work_flow.assign',
                'activity.view', 'calendar.view',
            ],
            'sales' => [
                'work_flow.view', 'work_flow.assign',
                'projects.view', 'tasks.view', 'leads.view', 'leads.create', 'leads.update',
            ],
            'social_media_manager' => [
                'dashboard.view', 'profile.view', 'profile.update',
                'projects.view', 'tasks.view', 'tasks.work', 'tasks.comment',
                'work_flow.view', 'work_flow.assign', 'clients.view',
                'deliverables.view', 'calendar.view', 'activity.view',
            ],
            'editor' => [
                'dashboard.view', 'profile.view', 'profile.update',
                'projects.view', 'tasks.view', 'tasks.work', 'tasks.comment', 'tasks.attachments',
                'work_flow.view', 'deliverables.view', 'deliverables.update',
            ],
            'videographer' => [
                'dashboard.view', 'profile.view', 'profile.update',
                'projects.view', 'tasks.view', 'tasks.work', 'tasks.comment', 'tasks.attachments',
                'work_flow.view', 'deliverables.view',
            ],
            'site_locator' => [
                'dashboard.view', 'profile.view', 'profile.update',
                'projects.view', 'tasks.view', 'tasks.work', 'tasks.comment',
                'work_flow.view', 'bookings.view',
            ],
            'manager' => ['work_flow.view', 'work_flow.manage', 'work_flow.assign'],
            'project_manager' => ['work_flow.view', 'work_flow.assign'],
            'team_leader' => ['work_flow.view'],
            'employee' => ['work_flow.view'],
            'finance' => ['work_flow.view'],
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

    public static function enableFeatures(): void
    {
        $rows = Database::query('SELECT code, features FROM subscription_plans')->fetchAll();
        foreach ($rows as $row) {
            $features = json_decode((string) $row['features'], true);
            if (!is_array($features)) {
                continue;
            }
            $next = $features;
            if (!in_array('work_flow', $next, true)) {
                $next[] = 'work_flow';
            }
            if ($next !== $features) {
                Database::query(
                    'UPDATE subscription_plans SET features = ?, updated_at = ? WHERE code = ?',
                    [json_encode(array_values($next)), date('Y-m-d H:i:s'), $row['code']]
                );
            }
        }

        $organizations = Database::query(
            'SELECT id, enabled_features FROM organizations WHERE enabled_features IS NOT NULL AND enabled_features <> ""'
        )->fetchAll();
        foreach ($organizations as $organization) {
            $features = json_decode((string) $organization['enabled_features'], true);
            if (!is_array($features)) {
                continue;
            }
            $next = $features;
            if (!in_array('work_flow', $next, true)) {
                $next[] = 'work_flow';
            }
            if ($next !== $features) {
                Database::query(
                    'UPDATE organizations SET enabled_features = ? WHERE id = ?',
                    [json_encode(array_values($next)), (int) $organization['id']]
                );
            }
        }
    }

    public static function seedPipelineRoles(): void
    {
        $now = date('Y-m-d H:i:s');
        $orgs = Database::query('SELECT id FROM organizations')->fetchAll();
        foreach ($orgs as $org) {
            $orgId = (int) $org['id'];
            $count = (int) (Database::query(
                'SELECT COUNT(*) AS c FROM work_flow_roles WHERE organization_id = ?',
                [$orgId]
            )->fetch()['c'] ?? 0);
            if ($count > 0) {
                continue;
            }
            foreach (self::DEFAULT_PIPELINE_ROLES as $index => $name) {
                Database::query(
                    'INSERT INTO work_flow_roles (organization_id, name, description, sort_order, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, ?, ?)',
                    [$orgId, $name, $name . ' stage in the production workflow', $index + 1, $now, $now]
                );
            }
        }
    }

    public static function seedSampleUsers(): void
    {
        $org = Database::query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetch();
        if (!$org) {
            return;
        }
        $orgId = (int) $org['id'];
        $now = date('Y-m-d H:i:s');
        $password = password_hash('DemoPass123!', PASSWORD_DEFAULT);

        $users = [
            ['general_manager', 'Abel Bekele', 'gm@haaye.com', 'General Manager', 'GM-1001'],
            ['operations_manager', 'Meron Assefa', 'ops@haaye.com', 'Operations Manager', 'OPS-1001'],
            ['sales', 'Sara Sales', 'sales@haaye.com', 'Sales Executive', 'SALES-001'],
            ['social_media_manager', 'Hana Girma', 'social@haaye.com', 'Social Media Manager', 'SMM-1001'],
            ['editor', 'Yonatan Kebede', 'editor@haaye.com', 'Editor', 'EDT-1001'],
            ['videographer', 'Samuel Haile', 'video@haaye.com', 'Videographer', 'VID-1001'],
            ['site_locator', 'Ruth Desta', 'locator@haaye.com', 'Site Locator', 'LOC-1001'],
        ];

        foreach ($users as $item) {
            $role = Role::findBySlug($item[0]);
            if (!$role instanceof Role) {
                continue;
            }
            $existing = Database::query(
                'SELECT id FROM users WHERE organization_id = ? AND email = ? LIMIT 1',
                [$orgId, $item[2]]
            )->fetch();
            if ($existing) {
                Database::query(
                    'UPDATE users SET role_id = ?, name = ?, job_title = ?, employee_code = ?, status = ?, password_hash = ?, updated_at = ?
                     WHERE id = ?',
                    [$role->id, $item[1], $item[3], $item[4], 'active', $password, $now, (int) $existing['id']]
                );
                continue;
            }

            User::create([
                'organization_id' => $orgId,
                'role_id' => $role->id,
                'name' => $item[1],
                'email' => $item[2],
                'phone' => null,
                'job_title' => $item[3],
                'employee_code' => $item[4],
                'status' => 'active',
                'joining_date' => date('Y-m-d'),
                'password_hash' => $password,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public static function seedSampleTasks(): void
    {
        $org = Database::query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetch();
        if (!$org) {
            return;
        }
        $orgId = (int) $org['id'];
        $project = Database::query(
            'SELECT id FROM projects WHERE organization_id = ? ORDER BY id ASC LIMIT 1',
            [$orgId]
        )->fetch();
        if (!$project) {
            return;
        }
        $projectId = (int) $project['id'];

        $existing = (int) (Database::query(
            'SELECT COUNT(*) AS c FROM work_flow_tasks WHERE organization_id = ?',
            [$orgId]
        )->fetch()['c'] ?? 0);
        if ($existing > 0) {
            return;
        }

        $roles = Database::query(
            'SELECT id, name FROM work_flow_roles WHERE organization_id = ? ORDER BY sort_order ASC',
            [$orgId]
        )->fetchAll();
        $roleByName = [];
        foreach ($roles as $role) {
            $roleByName[(string) $role['name']] = (int) $role['id'];
        }

        $users = Database::query(
            "SELECT u.id, r.slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ?",
            [$orgId]
        )->fetchAll();
        $userBySlug = [];
        foreach ($users as $user) {
            $userBySlug[(string) $user['slug']] = (int) $user['id'];
        }

        $creator = $userBySlug['operations_manager'] ?? $userBySlug['owner'] ?? $userBySlug['admin'] ?? null;
        $now = date('Y-m-d H:i:s');
        $samples = [
            ['Confirm brief with client', 'Sales', 'sales', 'todo'],
            ['Scout and lock shoot location', 'Site Locator', 'site_locator', 'todo'],
            ['Capture main footage', 'Videographer', 'videographer', 'todo'],
            ['Edit highlight cut', 'Editor', 'editor', 'todo'],
            ['Schedule social posts', 'Social Media Manager', 'social_media_manager', 'todo'],
            ['Review and approve delivery', 'Operations Manager', 'operations_manager', 'todo'],
            ['Final sign-off', 'General Manager', 'general_manager', 'todo'],
        ];

        foreach ($samples as $sample) {
            Database::query(
                'INSERT INTO work_flow_tasks (
                    organization_id, project_id, work_flow_role_id, title, description,
                    assignee_user_id, status, due_date, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orgId,
                    $projectId,
                    $roleByName[$sample[1]] ?? null,
                    $sample[0],
                    'Sample workflow task for ' . $sample[1],
                    $userBySlug[$sample[2]] ?? null,
                    $sample[3],
                    date('Y-m-d', strtotime('+7 days')),
                    $creator,
                    $now,
                    $now,
                ]
            );
        }

        // Ensure sample assignees are on the project so scoped users can see it.
        foreach (['sales', 'site_locator', 'videographer', 'editor', 'social_media_manager', 'operations_manager', 'general_manager'] as $slug) {
            $userId = $userBySlug[$slug] ?? null;
            if ($userId === null) {
                continue;
            }
            $member = Database::query(
                'SELECT id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1',
                [$projectId, $userId]
            )->fetch();
            if ($member) {
                continue;
            }
            Database::query(
                'INSERT INTO project_members (organization_id, project_id, user_id, member_role, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [
                    $orgId,
                    $projectId,
                    $userId,
                    in_array($slug, ['general_manager', 'operations_manager'], true) ? 'manager' : 'member',
                    $now,
                ]
            );
        }
    }
}
