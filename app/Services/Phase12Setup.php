<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Phase12Setup
{
    public static function applySchema(): void
    {
        self::ensureTaskColumns();
        self::ensureBookingColumns();
        self::migrateWorkflowTasksIntoOps();
        self::ensureReviewStatus();
    }

    public static function seedPermissions(): void
    {
        Phase10Setup::seedPermissions();
        Phase9Setup::seedPermissions();
    }

    private static function ensureTaskColumns(): void
    {
        if (!self::columnExists('tasks', 'work_flow_role_id')) {
            Database::connection()->exec(
                'ALTER TABLE tasks ADD COLUMN work_flow_role_id BIGINT UNSIGNED NULL AFTER reporter_user_id'
            );
        }
        if (!self::columnExists('tasks', 'booking_id')) {
            Database::connection()->exec(
                'ALTER TABLE tasks ADD COLUMN booking_id BIGINT UNSIGNED NULL AFTER work_flow_role_id'
            );
        }
        self::ensureIndex('tasks', 'idx_tasks_work_flow_role', 'work_flow_role_id');
        self::ensureIndex('tasks', 'idx_tasks_booking', 'booking_id');
        self::ensureFk('tasks', 'fk_tasks_work_flow_role', 'work_flow_role_id', 'work_flow_roles', 'id');
        self::ensureFk('tasks', 'fk_tasks_booking', 'booking_id', 'bookings', 'id');
    }

    private static function ensureBookingColumns(): void
    {
        $columns = [
            'task_id' => 'BIGINT UNSIGNED NULL AFTER project_id',
            'checklist_company' => 'VARCHAR(180) NULL AFTER notes',
            'checklist_date' => 'DATE NULL AFTER checklist_company',
            'equipment_json' => 'LONGTEXT NULL AFTER checklist_date',
            'rentals_json' => 'LONGTEXT NULL AFTER equipment_json',
            'represented_name' => 'VARCHAR(160) NULL AFTER rentals_json',
            'represented_signed' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER represented_name',
        ];
        foreach ($columns as $name => $definition) {
            if (!self::columnExists('bookings', $name)) {
                Database::connection()->exec('ALTER TABLE bookings ADD COLUMN ' . $name . ' ' . $definition);
            }
        }
        self::ensureIndex('bookings', 'idx_bookings_task', 'task_id');
        self::ensureFk('bookings', 'fk_bookings_task', 'task_id', 'tasks', 'id');
    }

    private static function migrateWorkflowTasksIntoOps(): void
    {
        if (!self::tableExists('work_flow_tasks')) {
            return;
        }

        $rows = Database::query(
            'SELECT w.*, p.client_id
             FROM work_flow_tasks w
             INNER JOIN projects p ON p.id = w.project_id AND p.organization_id = w.organization_id
             WHERE NOT EXISTS (
                SELECT 1 FROM tasks t
                WHERE t.organization_id = w.organization_id
                  AND t.project_id = w.project_id
                  AND t.title = w.title
                  AND COALESCE(t.work_flow_role_id, 0) = COALESCE(w.work_flow_role_id, 0)
                  AND COALESCE(t.assignee_user_id, 0) = COALESCE(w.assignee_user_id, 0)
                  AND COALESCE(t.due_date, \'1970-01-01\') = COALESCE(w.due_date, \'1970-01-01\')
             )'
        )->fetchAll();

        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            Database::query(
                'INSERT INTO tasks (
                    organization_id, project_id, client_id, title, description,
                    assignee_user_id, reporter_user_id, work_flow_role_id, booking_id,
                    priority, status, start_date, due_date, progress, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NULL, ?, 0, ?, ?)',
                [
                    (int) $row['organization_id'],
                    (int) $row['project_id'],
                    (int) $row['client_id'],
                    (string) $row['title'],
                    $row['description'],
                    $row['assignee_user_id'],
                    $row['created_by'],
                    $row['work_flow_role_id'],
                    'medium',
                    (string) $row['status'],
                    $row['due_date'],
                    $row['created_at'] ?: $now,
                    $row['updated_at'] ?: $now,
                ]
            );
        }
    }

    private static function ensureReviewStatus(): void
    {
        $orgs = Database::query('SELECT id FROM organizations')->fetchAll();
        $now = date('Y-m-d H:i:s');
        foreach ($orgs as $org) {
            $orgId = (int) $org['id'];
            $exists = Database::query(
                'SELECT id FROM task_statuses WHERE organization_id = ? AND slug = ? LIMIT 1',
                [$orgId, 'review']
            )->fetch();
            if ($exists) {
                continue;
            }
            Database::query(
                'INSERT INTO task_statuses (organization_id, name, slug, color, sort_order, is_closed, is_system, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 0, 1, ?, ?)',
                [$orgId, 'Review', 'review', '#C2410C', 35, $now, $now]
            );
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        $row = Database::query(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, $column]
        )->fetch();

        return (bool) $row;
    }

    private static function tableExists(string $table): bool
    {
        $row = Database::query(
            'SELECT 1 AS ok FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            [$table]
        )->fetch();

        return (bool) $row;
    }

    private static function ensureIndex(string $table, string $index, string $column): void
    {
        $row = Database::query(
            'SELECT 1 AS ok FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        )->fetch();
        if ($row) {
            return;
        }
        Database::connection()->exec('ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` (`' . $column . '`)');
    }

    private static function ensureFk(string $table, string $fk, string $column, string $refTable, string $refColumn): void
    {
        $row = Database::query(
            'SELECT 1 AS ok FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\' LIMIT 1',
            [$table, $fk]
        )->fetch();
        if ($row) {
            return;
        }
        try {
            Database::connection()->exec(
                'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $fk . '` FOREIGN KEY (`' . $column . '`)
                 REFERENCES `' . $refTable . '`(`' . $refColumn . '`) ON DELETE SET NULL'
            );
        } catch (\Throwable) {
            // Ignore if engine/order prevents FK; columns still work.
        }
    }
}
