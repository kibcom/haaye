<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Expense extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $user_id = 0;
    public ?int $project_id = null;
    public ?int $client_id = null;
    public string $category = 'other';
    public string $amount = '0.00';
    public string $spent_on = '';
    public ?string $description = null;
    public string $status = 'pending';
    public ?int $decided_by = null;
    public ?string $decision_comment = null;
    public ?string $decided_at = null;
    public ?string $original_name = null;
    public ?string $stored_path = null;
    public ?string $mime_type = null;
    public ?int $size_bytes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $user_name = null;
    public ?string $project_name = null;
    public ?string $client_name = null;
    public ?string $decider_name = null;

    public static function table(): string
    {
        return 'expenses';
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return ['travel', 'meals', 'software', 'contractors', 'supplies', 'other'];
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE e.id = ? AND e.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function listVisible(int $organizationId, ?int $onlyUserId, bool $ownOnly, int $viewerId, ?string $status, int $limit = 200): array
    {
        [$where, $params] = self::scope($organizationId, $onlyUserId, $ownOnly, $viewerId);
        if ($status !== null && $status !== '') {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY e.spent_on DESC, e.id DESC LIMIT ' . (int) $limit,
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function approvedTotal(int $organizationId, ?int $projectId, ?int $onlyUserId, bool $ownOnly, int $viewerId): string
    {
        [$where, $params] = self::scope($organizationId, $onlyUserId, $ownOnly, $viewerId);
        $where[] = "e.status = 'approved'";
        if ($projectId !== null) {
            $where[] = 'e.project_id = ?';
            $params[] = $projectId;
        }
        $sum = Database::query(
            'SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE ' . implode(' AND ', $where),
            $params
        )->fetchColumn();

        return number_format((float) $sum, 2, '.', '');
    }

    public static function approvedForProject(int $organizationId, int $projectId): string
    {
        $sum = Database::query(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE organization_id = ? AND project_id = ? AND status = 'approved'",
            [$organizationId, $projectId]
        )->fetchColumn();

        return number_format((float) $sum, 2, '.', '');
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO expenses (
                organization_id, user_id, project_id, client_id, category, amount, spent_on, description, status,
                original_name, stored_path, mime_type, size_bytes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['user_id'],
                $data['project_id'] ?? null,
                $data['client_id'] ?? null,
                $data['category'],
                $data['amount'],
                $data['spent_on'],
                $data['description'] ?? null,
                $data['status'] ?? 'pending',
                $data['original_name'] ?? null,
                $data['stored_path'] ?? null,
                $data['mime_type'] ?? null,
                $data['size_bytes'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = ['status', 'decided_by', 'decision_comment', 'decided_at', 'updated_at'];
        $sets = [];
        $params = [];
        foreach ($allowed as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $data[$column];
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $params[] = $organizationId;
        Database::query('UPDATE expenses SET ' . implode(', ', $sets) . ' WHERE id = ? AND organization_id = ?', $params);
    }

    public static function deleteById(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM expenses WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public static function scope(int $organizationId, ?int $onlyUserId, bool $ownOnly, int $viewerId): array
    {
        $where = ['e.organization_id = ?'];
        $params = [$organizationId];
        if ($ownOnly) {
            $where[] = 'e.user_id = ?';
            $params[] = $viewerId;

            return [$where, $params];
        }
        if ($onlyUserId !== null) {
            $where[] = '(e.user_id = ? OR EXISTS (
                SELECT 1 FROM projects p
                WHERE p.id = e.project_id AND p.organization_id = e.organization_id
                AND (p.manager_user_id = ? OR EXISTS (
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
                ))
            ))';
            $params[] = $viewerId;
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }

    private static function selectSql(): string
    {
        return 'SELECT e.*, u.name AS user_name, p.name AS project_name, c.name AS client_name, d.name AS decider_name
            FROM expenses e
            INNER JOIN users u ON u.id = e.user_id
            LEFT JOIN projects p ON p.id = e.project_id
            LEFT JOIN clients c ON c.id = e.client_id
            LEFT JOIN users d ON d.id = e.decided_by';
    }
}
