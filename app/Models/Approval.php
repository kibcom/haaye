<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Approval extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public ?int $project_id = null;
    public ?int $client_id = null;
    public string $entity_type = '';
    public int $entity_id = 0;
    public string $entity_label = '';
    public string $approval_kind = 'internal';
    public string $from_status = '';
    public string $to_status = '';
    public string $status = 'pending';
    public ?int $requested_by = null;
    public ?int $decided_by = null;
    public ?string $decision_comment = null;
    public ?string $decided_at = null;
    public ?string $created_at = null;
    public ?string $requester_name = null;
    public ?string $decider_name = null;

    public static function table(): string
    {
        return 'approvals';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE a.id = ? AND a.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function pendingKind(int $organizationId, string $entityType, int $entityId, string $kind): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE a.organization_id = ? AND a.entity_type = ? AND a.entity_id = ? AND a.approval_kind = ? AND a.status = ? LIMIT 1',
            [$organizationId, $entityType, $entityId, $kind, 'pending']
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function pendingForEntity(int $organizationId, string $entityType, int $entityId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE a.organization_id = ? AND a.entity_type = ? AND a.entity_id = ? AND a.status = ? ORDER BY a.created_at DESC',
            [$organizationId, $entityType, $entityId, 'pending']
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function historyForEntity(int $organizationId, string $entityType, int $entityId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE a.organization_id = ? AND a.entity_type = ? AND a.entity_id = ? ORDER BY a.created_at DESC LIMIT 12',
            [$organizationId, $entityType, $entityId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function paginate(int $organizationId, int $page, int $perPage, ?string $status, ?string $kind, ?int $onlyClientId, ?int $onlyProjectUserId): array
    {
        $where = ['a.organization_id = ?'];
        $params = [$organizationId];

        if ($status !== null && $status !== '') {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'a.approval_kind = ?';
            $params[] = $kind;
        }
        if ($onlyClientId !== null) {
            $where[] = 'a.client_id = ? AND a.approval_kind = ?';
            $params[] = $onlyClientId;
            $params[] = 'client';
        }
        if ($onlyProjectUserId !== null) {
            $where[] = '(a.requested_by = ? OR EXISTS (
                SELECT 1 FROM projects p
                WHERE p.id = a.project_id AND p.organization_id = a.organization_id
                  AND (p.manager_user_id = ? OR EXISTS (
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
                  ))
            ))';
            $params[] = $onlyProjectUserId;
            $params[] = $onlyProjectUserId;
            $params[] = $onlyProjectUserId;
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::query('SELECT COUNT(*) FROM approvals a WHERE ' . $clause, $params)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            self::selectSql() . ' WHERE ' . $clause . ' ORDER BY a.created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            'items' => array_map([self::class, 'hydrate'], $rows),
        ];
    }

    public static function countPending(int $organizationId, ?int $onlyClientId, ?int $onlyProjectUserId, ?string $kind = null): int
    {
        $where = ['a.organization_id = ?', 'a.status = ?'];
        $params = [$organizationId, 'pending'];
        if ($kind !== null) {
            $where[] = 'a.approval_kind = ?';
            $params[] = $kind;
        }
        if ($onlyClientId !== null) {
            $where[] = 'a.client_id = ? AND a.approval_kind = ?';
            $params[] = $onlyClientId;
            $params[] = 'client';
        }
        if ($onlyProjectUserId !== null) {
            $where[] = '(a.requested_by = ? OR EXISTS (
                SELECT 1 FROM projects p
                WHERE p.id = a.project_id AND p.organization_id = a.organization_id
                  AND (p.manager_user_id = ? OR EXISTS (
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
                  ))
            ))';
            $params[] = $onlyProjectUserId;
            $params[] = $onlyProjectUserId;
            $params[] = $onlyProjectUserId;
        }

        return (int) Database::query('SELECT COUNT(*) FROM approvals a WHERE ' . implode(' AND ', $where), $params)->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO approvals (
                organization_id, project_id, client_id, entity_type, entity_id, entity_label, approval_kind,
                from_status, to_status, status, requested_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['project_id'] ?? null,
                $data['client_id'] ?? null,
                $data['entity_type'],
                $data['entity_id'],
                $data['entity_label'],
                $data['approval_kind'],
                $data['from_status'],
                $data['to_status'],
                'pending',
                $data['requested_by'] ?? null,
                $data['created_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function decide(int $id, int $organizationId, array $data): void
    {
        Database::query(
            'UPDATE approvals SET status = ?, decided_by = ?, decision_comment = ?, decided_at = ? WHERE id = ? AND organization_id = ? AND status = ?',
            [
                $data['status'],
                $data['decided_by'],
                $data['decision_comment'],
                $data['decided_at'],
                $id,
                $organizationId,
                'pending',
            ]
        );
    }

    /** @return list<self> */
    public static function cancelPendingForEntity(int $organizationId, string $entityType, int $entityId, int $actorId, string $comment): array
    {
        $pending = self::pendingForEntity($organizationId, $entityType, $entityId);
        if ($pending === []) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $cancelled = [];
        foreach ($pending as $approval) {
            self::decide((int) $approval->id, $organizationId, [
                'status' => 'cancelled',
                'decided_by' => $actorId,
                'decision_comment' => $comment,
                'decided_at' => $now,
            ]);
            $cancelled[] = $approval;
        }

        return $cancelled;
    }

    private static function selectSql(): string
    {
        return 'SELECT a.*, ru.name AS requester_name, du.name AS decider_name
                FROM approvals a
                LEFT JOIN users ru ON ru.id = a.requested_by
                LEFT JOIN users du ON du.id = a.decided_by';
    }
}
