<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class WorkflowTransition extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $entity_type = '';
    public string $from_status = '';
    public string $to_status = '';
    public string $requires_approval = 'none';
    public ?string $created_at = null;

    public static function table(): string
    {
        return 'workflow_transitions';
    }

    /** @return list<self> */
    public static function forOrganization(int $organizationId, ?string $entityType = null): array
    {
        $sql = 'SELECT * FROM workflow_transitions WHERE organization_id = ?';
        $params = [$organizationId];
        if ($entityType !== null && $entityType !== '') {
            $sql .= ' AND entity_type = ?';
            $params[] = $entityType;
        }
        $sql .= ' ORDER BY entity_type ASC, from_status ASC, to_status ASC';
        $rows = Database::query($sql, $params)->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM workflow_transitions WHERE id = ? AND organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function ensureDefaults(int $organizationId): void
    {
        $now = date('Y-m-d H:i:s');
        $sets = [
            'task' => [
                ['backlog', 'todo', 'none'],
                ['todo', 'in_progress', 'none'],
                ['todo', 'backlog', 'none'],
                ['in_progress', 'review', 'internal'],
                ['in_progress', 'todo', 'none'],
                ['review', 'in_progress', 'none'],
                ['review', 'completed', 'internal'],
            ],
            'deliverable' => [
                ['draft', 'internal_review', 'internal'],
                ['internal_review', 'client_review', 'none'],
                ['client_review', 'approved', 'client'],
                ['changes_requested', 'internal_review', 'none'],
                ['approved', 'delivered', 'none'],
            ],
        ];

        foreach ($sets as $type => $rows) {
            if (self::hasAny($organizationId, $type)) {
                continue;
            }
            foreach ($rows as $row) {
                self::create([
                    'organization_id' => $organizationId,
                    'entity_type' => $type,
                    'from_status' => $row[0],
                    'to_status' => $row[1],
                    'requires_approval' => $row[2],
                    'created_at' => $now,
                ]);
            }
        }
    }

    public static function hasAny(int $organizationId, string $entityType): bool
    {
        return Database::query(
            'SELECT id FROM workflow_transitions WHERE organization_id = ? AND entity_type = ? LIMIT 1',
            [$organizationId, $entityType]
        )->fetch() !== false;
    }

    public static function match(int $organizationId, string $entityType, string $from, string $to): ?self
    {
        $row = Database::query(
            'SELECT * FROM workflow_transitions
             WHERE organization_id = ? AND entity_type = ? AND from_status = ? AND to_status = ?
             LIMIT 1',
            [$organizationId, $entityType, $from, $to]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function exists(int $organizationId, string $entityType, string $from, string $to): bool
    {
        return self::match($organizationId, $entityType, $from, $to) instanceof self;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO workflow_transitions (organization_id, entity_type, from_status, to_status, requires_approval, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['entity_type'],
                $data['from_status'],
                $data['to_status'],
                $data['requires_approval'],
                $data['created_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query(
            'DELETE FROM workflow_transitions WHERE id = ? AND organization_id = ?',
            [$id, $organizationId]
        );
    }
}
