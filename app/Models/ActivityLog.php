<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ActivityLog extends Model
{
    public ?int $id = null;
    public ?int $organization_id = null;
    public ?int $user_id = null;
    public string $action = '';
    public ?string $entity_type = null;
    public ?int $entity_id = null;
    public string $description = '';
    public ?string $ip_address = null;
    public ?string $created_at = null;
    public ?string $actor_name = null;
    public ?string $actor_role = null;

    public static function table(): string
    {
        return 'activity_logs';
    }

    public static function record(
        ?int $organizationId,
        ?int $userId,
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null
    ): void {
        Database::query(
            'INSERT INTO activity_logs (
                organization_id, user_id, action, entity_type, entity_id, description, ip_address, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $organizationId,
                $userId,
                $action,
                $entityType,
                $entityId,
                $description,
                request_ip(),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    /** @return list<self> */
    public static function recentForEntity(int $organizationId, string $entityType, int $entityId, int $limit = 8): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE a.organization_id = ? AND a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.created_at DESC
             LIMIT ' . (int) $limit,
            [$organizationId, $entityType, $entityId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @param ?int $actorFilter Filter by who performed the action (managers only).
     * @param ?int $relatedUserId When set, only activity by/for this person (assignee, reporter, manager, approval party).
     * @return array{items: list<self>, total: int}
     */
    public static function listForOrganization(
        int $organizationId,
        ?int $actorFilter,
        int $limit,
        int $offset,
        ?int $relatedUserId = null
    ): array {
        [$where, $params] = self::scopeWhere($organizationId, $actorFilter, $relatedUserId);

        $total = (int) Database::query(
            'SELECT COUNT(*) FROM activity_logs a WHERE ' . $where,
            $params
        )->fetchColumn();

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . $where . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        )->fetchAll();

        return [
            'items' => array_map([self::class, 'hydrate'], $rows),
            'total' => $total,
        ];
    }

    /**
     * @return array{0:string,1:list<mixed>}
     */
    private static function scopeWhere(int $organizationId, ?int $actorFilter, ?int $relatedUserId): array
    {
        $where = ['a.organization_id = ?'];
        $params = [$organizationId];

        if ($actorFilter !== null && $actorFilter > 0) {
            $where[] = 'a.user_id = ?';
            $params[] = $actorFilter;
        }

        if ($relatedUserId !== null && $relatedUserId > 0) {
            // Own actions + anything assigned/approved/related to this employee.
            $where[] = '(
                a.user_id = ?
                OR (
                    a.entity_type = \'task\' AND a.entity_id IN (
                        SELECT t.id FROM tasks t
                        WHERE t.organization_id = ?
                          AND (t.assignee_user_id = ? OR t.reporter_user_id = ? OR t.manager_user_id = ?)
                    )
                )
                OR (
                    a.entity_type = \'approval\' AND a.entity_id IN (
                        SELECT ap.id FROM approvals ap
                        WHERE ap.organization_id = ?
                          AND (ap.requested_by = ? OR ap.decided_by = ?)
                    )
                )
                OR (
                    a.entity_type = \'task\' AND a.entity_id IN (
                        SELECT ap.entity_id FROM approvals ap
                        WHERE ap.organization_id = ? AND ap.entity_type = \'task\'
                          AND (ap.requested_by = ? OR ap.decided_by = ?)
                    )
                )
                OR (
                    a.entity_type = \'deliverable\' AND a.entity_id IN (
                        SELECT ap.entity_id FROM approvals ap
                        WHERE ap.organization_id = ? AND ap.entity_type = \'deliverable\'
                          AND (ap.requested_by = ? OR ap.decided_by = ?)
                    )
                )
                OR (
                    a.entity_type = \'workflow_run\' AND a.entity_id IN (
                        SELECT DISTINCT t.workflow_run_id FROM tasks t
                        WHERE t.organization_id = ? AND t.assignee_user_id = ?
                          AND t.workflow_run_id IS NOT NULL
                    )
                )
                OR (
                    a.entity_type = \'workflow_run_stage\' AND a.entity_id IN (
                        SELECT s.id FROM workflow_run_stages s
                        WHERE s.organization_id = ? AND s.assignee_user_id = ?
                    )
                )
                OR (
                    a.entity_type = \'project\' AND a.entity_id IN (
                        SELECT p.id FROM projects p
                        WHERE p.organization_id = ?
                          AND (
                            p.manager_user_id = ?
                            OR EXISTS (
                                SELECT 1 FROM project_members pm
                                WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
                            )
                        )
                    )
                )
                OR (
                    a.entity_type = \'booking\' AND a.entity_id IN (
                        SELECT b.id FROM bookings b
                        WHERE b.organization_id = ?
                          AND (
                            b.created_by = ?
                            OR b.submitted_to_user_id = ?
                            OR b.reviewed_by = ?
                          )
                    )
                )
                OR (
                    a.entity_type = \'deliverable\' AND a.entity_id IN (
                        SELECT d.id FROM deliverables d
                        WHERE d.organization_id = ?
                          AND d.assignee_user_id = ?
                    )
                )
            )';
            array_push(
                $params,
                $relatedUserId,
                // tasks assigned / reported / managed
                $organizationId,
                $relatedUserId,
                $relatedUserId,
                $relatedUserId,
                // approval records involving user
                $organizationId,
                $relatedUserId,
                $relatedUserId,
                // task activity via approvals
                $organizationId,
                $relatedUserId,
                $relatedUserId,
                // deliverable activity via approvals
                $organizationId,
                $relatedUserId,
                $relatedUserId,
                // pipeline runs with assigned tasks
                $organizationId,
                $relatedUserId,
                // pipeline stages assigned
                $organizationId,
                $relatedUserId,
                // projects managed or member of
                $organizationId,
                $relatedUserId,
                $relatedUserId,
                // bookings created, submitted to, or reviewed by
                $organizationId,
                $relatedUserId,
                $relatedUserId,
                $relatedUserId,
                // deliverables assigned
                $organizationId,
                $relatedUserId
            );
        }

        return [implode(' AND ', $where), $params];
    }

    /** @return list<array{id: int, name: string}> */
    public static function employees(int $organizationId): array
    {
        $rows = Database::query(
            'SELECT id, name FROM users WHERE organization_id = ? ORDER BY name ASC',
            [$organizationId]
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ], $rows);
    }

    /** @return list<self> */
    public static function recentForOrganization(int $organizationId, int $limit = 8, ?int $relatedUserId = null): array
    {
        [$where, $params] = self::scopeWhere($organizationId, null, $relatedUserId);
        $rows = Database::query(
            self::selectSql() . ' WHERE ' . $where . '
             ORDER BY a.created_at DESC
             LIMIT ' . (int) $limit,
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function recentPlatform(int $limit = 8): array
    {
        $rows = Database::query(
            self::selectSql() . ' ORDER BY a.created_at DESC
             LIMIT ' . (int) $limit
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    private static function selectSql(): string
    {
        return 'SELECT a.*, u.name AS actor_name, r.name AS actor_role
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN roles r ON r.id = u.role_id';
    }
}
