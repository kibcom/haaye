<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Milestone extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $project_id = 0;
    public string $name = '';
    public ?string $description = null;
    public ?string $due_date = null;
    public string $status = 'open';
    public int $progress = 0;
    public ?int $owner_user_id = null;
    public ?string $completed_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $owner_name = null;

    public static function table(): string
    {
        return 'milestones';
    }

    /** @return list<self> */
    public static function forProject(int $projectId, int $organizationId): array
    {
        $rows = Database::query(
            'SELECT m.*, u.name AS owner_name
             FROM milestones m
             LEFT JOIN users u ON u.id = m.owner_user_id AND u.organization_id = m.organization_id
             WHERE m.project_id = ? AND m.organization_id = ?
             ORDER BY m.due_date IS NULL, m.due_date ASC, m.id ASC',
            [$projectId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function findInProject(int $id, int $projectId, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT m.*, u.name AS owner_name
             FROM milestones m
             LEFT JOIN users u ON u.id = m.owner_user_id AND u.organization_id = m.organization_id
             WHERE m.id = ? AND m.project_id = ? AND m.organization_id = ?
             LIMIT 1',
            [$id, $projectId, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function countForProject(int $projectId, int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM milestones WHERE project_id = ? AND organization_id = ?',
            [$projectId, $organizationId]
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO milestones (
                organization_id, project_id, name, description, due_date, status, progress,
                owner_user_id, completed_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['project_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['due_date'] ?? null,
                $data['status'] ?? 'open',
                $data['progress'] ?? 0,
                $data['owner_user_id'] ?? null,
                $data['completed_at'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $fields = [];
        $params = [];
        foreach ($data as $column => $value) {
            $fields[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $params[] = $organizationId;
        Database::query(
            'UPDATE milestones SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInProject(int $id, int $projectId, int $organizationId): void
    {
        Database::query(
            'DELETE FROM milestones WHERE id = ? AND project_id = ? AND organization_id = ?',
            [$id, $projectId, $organizationId]
        );
    }
}
