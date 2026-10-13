<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStatus;
use App\Models\User;

final class Phase4Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/004_phase4_tasks.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 4 migration file is missing.');
        }

        $pdo = Database::connection();
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Tasks', 'View tasks', 'tasks.view'],
            ['Tasks', 'Create tasks', 'tasks.create'],
            ['Tasks', 'Update tasks', 'tasks.update'],
            ['Tasks', 'Assign tasks', 'tasks.assign'],
            ['Tasks', 'Update assigned task status', 'tasks.work'],
            ['Tasks', 'Comment on tasks', 'tasks.comment'],
            ['Tasks', 'Attach files to tasks', 'tasks.attachments'],
            ['Tasks', 'Manage task statuses', 'tasks.statuses.manage'],
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

        $all = [
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'tasks.comment', 'tasks.attachments', 'tasks.statuses.manage',
        ];
        $delivery = [
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'tasks.comment', 'tasks.attachments',
        ];
        $map = [
            'owner' => $all,
            'admin' => $all,
            'manager' => $delivery,
            'project_manager' => $delivery,
            'team_leader' => $delivery,
            'employee' => ['tasks.view', 'tasks.work', 'tasks.comment', 'tasks.attachments'],
            'finance' => ['tasks.view'],
            'sales' => ['tasks.view'],
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
            TaskStatus::ensureDefaults((int) $row['id']);
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
        TaskStatus::ensureDefaults($orgId);

        $manager = User::findByEmail('manager@haaye.com');
        $employee = User::findByEmail('employee@haaye.com');
        if (!$manager instanceof User || !$employee instanceof User) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $helios = Database::query(
            'SELECT id, client_id FROM projects WHERE organization_id = ? AND code = ? LIMIT 1',
            [$orgId, 'ZMN-BR']
        )->fetch();

        if ($helios && Task::countForProject((int) $helios['id'], $orgId) === 0) {
            $projectId = (int) $helios['id'];
            $clientId = (int) $helios['client_id'];
            $headlineId = Task::create([
                'organization_id' => $orgId,
                'project_id' => $projectId,
                'client_id' => $clientId,
                'title' => 'Write homepage headline',
                'description' => 'Draft two options from the discovery workshop.',
                'assignee_user_id' => $employee->id,
                'reporter_user_id' => $manager->id,
                'priority' => 'high',
                'status' => 'in_progress',
                'start_date' => '2026-10-01',
                'due_date' => '2026-10-20',
                'progress' => 45,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Task::create([
                'organization_id' => $orgId,
                'project_id' => $projectId,
                'client_id' => $clientId,
                'title' => 'Moodboard review',
                'description' => 'Prepare the two creative directions for the client.',
                'assignee_user_id' => $manager->id,
                'reporter_user_id' => $manager->id,
                'priority' => 'medium',
                'status' => 'review',
                'start_date' => '2026-10-16',
                'due_date' => '2026-10-28',
                'progress' => 70,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Task::create([
                'organization_id' => $orgId,
                'project_id' => $projectId,
                'client_id' => $clientId,
                'title' => 'Collect brand assets',
                'description' => 'Logos, fonts, and previous campaign files.',
                'assignee_user_id' => $employee->id,
                'reporter_user_id' => $manager->id,
                'priority' => 'low',
                'status' => 'completed',
                'start_date' => '2026-10-01',
                'due_date' => '2026-10-08',
                'progress' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            TaskComment::create([
                'organization_id' => $orgId,
                'task_id' => $headlineId,
                'user_id' => $manager->id,
                'body' => 'Use the shorter headline from the workshop.',
                'created_at' => $now,
            ]);
        }

        $brightline = Database::query(
            'SELECT id, client_id FROM projects WHERE organization_id = ? AND code = ? LIMIT 1',
            [$orgId, 'BSH-ONB']
        )->fetch();

        if ($brightline && Task::countForProject((int) $brightline['id'], $orgId) === 0) {
            Task::create([
                'organization_id' => $orgId,
                'project_id' => (int) $brightline['id'],
                'client_id' => (int) $brightline['client_id'],
                'title' => 'Confirm site access',
                'description' => 'Check hosting credentials with the client before build work resumes.',
                'assignee_user_id' => $manager->id,
                'reporter_user_id' => $manager->id,
                'priority' => 'medium',
                'status' => 'todo',
                'due_date' => '2026-11-12',
                'progress' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
