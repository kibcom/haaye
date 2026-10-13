<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Phase6Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/006_phase6_time.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 6 migration file is missing.');
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
            ['Time', 'View own time', 'time.view'],
            ['Time', 'View team time', 'time.view_all'],
            ['Time', 'Log time', 'time.create'],
            ['Time', 'Update own time', 'time.update'],
            ['Time', 'Manage team time', 'time.manage'],
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

        $lead = ['time.view', 'time.view_all', 'time.create', 'time.update', 'time.manage'];
        $map = [
            'owner' => $lead,
            'admin' => $lead,
            'manager' => $lead,
            'project_manager' => $lead,
            'team_leader' => ['time.view', 'time.create', 'time.update'],
            'employee' => ['time.view', 'time.create', 'time.update'],
            'finance' => ['time.view', 'time.view_all'],
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
        $org = Database::query("SELECT id FROM organizations WHERE slug = 'abay-consulting' LIMIT 1")->fetch();
        if (!$org) {
            return;
        }

        $orgId = (int) $org['id'];
        $existing = (int) Database::query('SELECT COUNT(*) FROM time_entries WHERE organization_id = ?', [$orgId])->fetchColumn();
        if ($existing > 0) {
            return;
        }

        $liam = self::userId('employee@haaye.com');
        $priya = self::userId('manager@haaye.com');
        $headline = self::task($orgId, 'Write homepage headline');
        $moodboard = self::task($orgId, 'Moodboard review');
        $assets = self::task($orgId, 'Collect brand assets');
        $now = date('Y-m-d H:i:s');

        $rows = [];
        if ($liam && $headline) {
            $rows[] = self::row($orgId, $liam, $headline, '2026-09-24 09:00:00', '2026-09-24 10:30:00', 90, 'Drafted headline options.', $now);
        }
        if ($priya && $moodboard) {
            $rows[] = self::row($orgId, $priya, $moodboard, '2026-09-25 13:00:00', '2026-09-25 15:00:00', 120, 'Reviewed the moodboard.', $now);
        }
        if ($liam && $assets) {
            $rows[] = self::row($orgId, $liam, $assets, '2026-09-22 10:00:00', '2026-09-22 10:45:00', 45, 'Collected logo files from the client.', $now);
        }

        foreach ($rows as $row) {
            Database::query(
                'INSERT INTO time_entries (
                    organization_id, user_id, project_id, task_id, client_id,
                    started_at, ended_at, duration_minutes, note, billable, source, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'manual\', ?, ?)',
                $row
            );
        }
    }

    private static function userId(string $email): ?int
    {
        $row = Database::query('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])->fetch();

        return $row ? (int) $row['id'] : null;
    }

    /** @return array{id: int, project_id: int, client_id: int}|null */
    private static function task(int $organizationId, string $title): ?array
    {
        $row = Database::query(
            'SELECT id, project_id, client_id FROM tasks WHERE organization_id = ? AND title = ? LIMIT 1',
            [$organizationId, $title]
        )->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'client_id' => (int) $row['client_id'],
        ];
    }

    /**
     * @param array{id: int, project_id: int, client_id: int} $task
     * @return list<mixed>
     */
    private static function row(int $organizationId, int $userId, array $task, string $start, string $end, int $minutes, string $note, string $now): array
    {
        return [
            $organizationId,
            $userId,
            $task['project_id'],
            $task['id'],
            $task['client_id'],
            $start,
            $end,
            $minutes,
            $note,
            $now,
            $now,
        ];
    }
}
