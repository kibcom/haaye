<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\TaskStatus;

final class Phase14Setup
{
    public static function applySchema(): void
    {
        $pdo = Database::connection();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                progression_mode ENUM('automatic','manual','hybrid') NOT NULL DEFAULT 'automatic',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_wf_tpl_org (organization_id),
                CONSTRAINT fk_wf_tpl_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_template_stages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                template_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                instructions TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                work_flow_role_id BIGINT UNSIGNED NULL,
                default_assignee_user_id BIGINT UNSIGNED NULL,
                requires_approval TINYINT(1) NOT NULL DEFAULT 1,
                approver_user_id BIGINT UNSIGNED NULL,
                auto_advance TINYINT(1) NOT NULL DEFAULT 1,
                deliverables_json LONGTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_wf_tpl_stage_tpl (template_id, sort_order),
                CONSTRAINT fk_wf_tpl_stage_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_wf_tpl_stage_tpl FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                template_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                client_id BIGINT UNSIGNED NOT NULL,
                lead_id BIGINT UNSIGNED NULL,
                name VARCHAR(180) NOT NULL,
                status ENUM('active','paused','completed','cancelled') NOT NULL DEFAULT 'active',
                progression_mode ENUM('automatic','manual','hybrid') NOT NULL DEFAULT 'automatic',
                current_stage_id BIGINT UNSIGNED NULL,
                started_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                INDEX idx_wf_run_project (project_id),
                INDEX idx_wf_run_status (organization_id, status),
                CONSTRAINT fk_wf_run_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_wf_run_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_run_stages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                run_id BIGINT UNSIGNED NOT NULL,
                template_stage_id BIGINT UNSIGNED NULL,
                name VARCHAR(160) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('pending','ready','in_progress','submitted','changes_requested','approved','skipped','cancelled') NOT NULL DEFAULT 'pending',
                task_id BIGINT UNSIGNED NULL,
                work_flow_role_id BIGINT UNSIGNED NULL,
                assignee_user_id BIGINT UNSIGNED NULL,
                requires_approval TINYINT(1) NOT NULL DEFAULT 1,
                approver_user_id BIGINT UNSIGNED NULL,
                auto_advance TINYINT(1) NOT NULL DEFAULT 1,
                instructions TEXT NULL,
                deliverables_json LONGTEXT NULL,
                activated_at DATETIME NULL,
                submitted_at DATETIME NULL,
                approved_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_wf_run_stage_run (run_id, sort_order),
                INDEX idx_wf_run_stage_task (task_id),
                CONSTRAINT fk_wf_run_stage_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_wf_run_stage_run FOREIGN KEY (run_id) REFERENCES workflow_runs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::ensureTaskColumns();
        self::ensureLifecycleStatusesForAllOrgs();
        self::seedDefaultTemplateForOrgs();
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Pipeline Workflows', 'View pipeline workflow templates & runs', 'pipeline.view'],
            ['Pipeline Workflows', 'Manage workflow templates', 'pipeline.templates'],
            ['Pipeline Workflows', 'Start and manage project workflow runs', 'pipeline.manage'],
            ['Pipeline Workflows', 'Override / skip workflow stages', 'pipeline.override'],
            ['Tasks', 'Submit task work for approval', 'tasks.submit'],
            ['Tasks', 'Approve or request changes on task submissions', 'tasks.approve'],
            ['Workspace', 'Employee workspace (My Work)', 'workspace.view'],
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

        $all = array_keys($permissionIds);
        $map = [
            'owner' => $all,
            'admin' => $all,
            'general_manager' => $all,
            'operations_manager' => [
                'pipeline.view', 'pipeline.templates', 'pipeline.manage', 'pipeline.override',
                'tasks.submit', 'tasks.approve', 'workspace.view',
                'approvals.view', 'approvals.decide',
            ],
            'manager' => [
                'pipeline.view', 'pipeline.manage', 'tasks.submit', 'tasks.approve', 'workspace.view',
                'approvals.view', 'approvals.decide',
            ],
            'project_manager' => [
                'pipeline.view', 'pipeline.manage', 'tasks.submit', 'tasks.approve', 'workspace.view',
                'approvals.view', 'approvals.decide',
            ],
            'team_leader' => ['pipeline.view', 'tasks.submit', 'workspace.view'],
            'sales' => ['pipeline.view', 'tasks.submit', 'workspace.view'],
            'social_media_manager' => ['pipeline.view', 'tasks.submit', 'workspace.view'],
            'editor' => ['tasks.submit', 'workspace.view'],
            'videographer' => ['tasks.submit', 'workspace.view'],
            'site_locator' => ['tasks.submit', 'workspace.view'],
            'employee' => ['tasks.submit', 'workspace.view'],
        ];

        foreach ($map as $slug => $slugs) {
            $role = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])->fetch();
            if (!$role) {
                continue;
            }
            $roleId = (int) $role['id'];
            foreach ($slugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? 0;
                if ($permissionId < 1) {
                    $existingPerm = Database::query(
                        'SELECT id FROM permissions WHERE slug = ? LIMIT 1',
                        [$permissionSlug]
                    )->fetch();
                    $permissionId = $existingPerm ? (int) $existingPerm['id'] : 0;
                }
                AuthorizationService::grantPermissionToDefaultRole($roleId, $permissionId);
            }
        }

        // Refresh role descriptions for management clarity.
        $descriptions = [
            'general_manager' => 'CEO/GM: full visibility, approvals, pipeline workflows, and overrides when permitted.',
            'operations_manager' => 'Operations: assign/approve work and manage pipeline runs; user/settings access is separately configurable.',
        ];
        foreach ($descriptions as $slug => $description) {
            Database::query(
                'UPDATE roles SET description = ? WHERE slug = ? AND scope = ?',
                [$description, $slug, 'organization']
            );
        }
    }

    private static function ensureTaskColumns(): void
    {
        $columns = [
            'lead_id' => 'BIGINT UNSIGNED NULL AFTER client_id',
            'manager_user_id' => 'BIGINT UNSIGNED NULL AFTER reporter_user_id',
            'workflow_run_id' => 'BIGINT UNSIGNED NULL AFTER booking_id',
            'workflow_run_stage_id' => 'BIGINT UNSIGNED NULL AFTER workflow_run_id',
            'completion_criteria' => 'TEXT NULL AFTER description',
            'estimated_hours' => 'DECIMAL(8,2) NULL AFTER progress',
            'submitted_at' => 'DATETIME NULL AFTER estimated_hours',
            'approved_at' => 'DATETIME NULL AFTER submitted_at',
            'approved_by' => 'BIGINT UNSIGNED NULL AFTER approved_at',
            'submission_note' => 'TEXT NULL AFTER approved_by',
        ];
        foreach ($columns as $name => $definition) {
            if (!self::columnExists('tasks', $name)) {
                Database::connection()->exec('ALTER TABLE tasks ADD COLUMN ' . $name . ' ' . $definition);
            }
        }
        self::ensureIndex('tasks', 'idx_tasks_workflow_run', 'workflow_run_id');
        self::ensureIndex('tasks', 'idx_tasks_workflow_stage', 'workflow_run_stage_id');
        self::ensureIndex('tasks', 'idx_tasks_manager', 'manager_user_id');
    }

    private static function ensureLifecycleStatusesForAllOrgs(): void
    {
        $orgs = Database::query('SELECT id FROM organizations')->fetchAll();
        foreach ($orgs as $org) {
            TaskStatus::ensureDefaults((int) $org['id']);
            TaskStatus::ensureLifecycleStatuses((int) $org['id']);
        }
    }

    private static function seedDefaultTemplateForOrgs(): void
    {
        if (!self::tableExists('workflow_templates') || !self::tableExists('work_flow_roles')) {
            return;
        }

        $orgs = Database::query('SELECT id FROM organizations')->fetchAll();
        $now = date('Y-m-d H:i:s');
        $stages = [
            ['Site Location', 'site_locator', 'Visit or research the location. Upload photos, notes, and access details.'],
            ['Video Shooting', 'videographer', 'Record required footage and upload raw deliverables.'],
            ['Content Creation', 'social_media_manager', 'Prepare captions, scripts, or creative assets.'],
            ['Video Editing', 'editor', 'Upload the edited video and supporting files.'],
            ['Social Media Management', 'social_media_manager', 'Prepare or publish approved content; add evidence links.'],
            ['Final Review', 'operations_manager', 'Manager reviews completed deliverables and closes the pipeline.'],
        ];

        foreach ($orgs as $org) {
            $orgId = (int) $org['id'];
            $exists = Database::query(
                'SELECT id FROM workflow_templates WHERE organization_id = ? LIMIT 1',
                [$orgId]
            )->fetch();
            if ($exists) {
                continue;
            }

            Database::query(
                'INSERT INTO workflow_templates (organization_id, name, description, progression_mode, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, NULL, ?, ?)',
                [
                    $orgId,
                    'Marketing Campaign Pipeline',
                    'Default production sequence: location → shoot → content → edit → social → final review.',
                    'automatic',
                    $now,
                    $now,
                ]
            );
            $templateId = (int) Database::lastInsertId();

            $order = 1;
            foreach ($stages as $stage) {
                $roleId = self::roleIdByNameHint($orgId, $stage[1]);
                $auto = $order < count($stages) ? 1 : 0;
                Database::query(
                    'INSERT INTO workflow_template_stages (
                        organization_id, template_id, name, description, instructions, sort_order,
                        work_flow_role_id, requires_approval, auto_advance, is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
                    [
                        $orgId,
                        $templateId,
                        $stage[0],
                        $stage[2],
                        $stage[2],
                        $order,
                        $roleId,
                        $auto,
                        $now,
                        $now,
                    ]
                );
                $order++;
            }
        }
    }

    private static function roleIdByNameHint(int $organizationId, string $hint): ?int
    {
        $map = [
            'site_locator' => '%Site%',
            'videographer' => '%Video%',
            'editor' => '%Edit%',
            'social_media_manager' => '%Social%',
            'operations_manager' => '%Operations%',
        ];
        $like = $map[$hint] ?? ('%' . $hint . '%');
        $row = Database::query(
            'SELECT id FROM work_flow_roles WHERE organization_id = ? AND name LIKE ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 1',
            [$organizationId, $like]
        )->fetch();

        return $row ? (int) $row['id'] : null;
    }

    private static function columnExists(string $table, string $column): bool
    {
        return Database::query(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, $column]
        )->fetch() !== false;
    }

    private static function tableExists(string $table): bool
    {
        return Database::query(
            'SELECT 1 AS ok FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            [$table]
        )->fetch() !== false;
    }

    private static function ensureIndex(string $table, string $index, string $column): void
    {
        $exists = Database::query(
            'SELECT 1 AS ok FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        )->fetch();
        if ($exists) {
            return;
        }
        try {
            Database::connection()->exec('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' (' . $column . ')');
        } catch (\Throwable) {
            // Index may already exist under another name.
        }
    }
}
