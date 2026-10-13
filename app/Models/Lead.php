<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Lead extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $name = '';
    public ?string $company = null;
    public ?string $email = null;
    public ?string $phone = null;
    public string $source = 'other';
    public string $status = 'new';
    public ?string $estimated_value = null;
    public ?string $notes = null;
    public ?int $owner_user_id = null;
    public ?int $client_id = null;
    public ?int $project_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $owner_name = null;
    public ?string $client_name = null;
    public ?string $project_name = null;

    public static function table(): string
    {
        return 'leads';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(self::selectSql() . ' WHERE l.id = ? AND l.organization_id = ? LIMIT 1', [$id, $organizationId])->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function listFor(
        int $organizationId,
        ?string $status = null,
        ?string $search = null,
        ?int $ownerUserId = null
    ): array {
        $where = ['l.organization_id = ?'];
        $params = [$organizationId];
        if ($status !== null && $status !== '') {
            $where[] = 'l.status = ?';
            $params[] = $status;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(l.name LIKE ? OR l.company LIKE ? OR l.email LIKE ? OR l.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $where[] = 'l.owner_user_id = ?';
            $params[] = $ownerUserId;
        }

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY l.updated_at DESC, l.id DESC',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return array<string, int> */
    public static function counts(int $organizationId, ?int $ownerUserId = null): array
    {
        $counts = ['new' => 0, 'contacted' => 0, 'qualified' => 0, 'converted' => 0, 'lost' => 0];
        $where = 'organization_id = ?';
        $params = [$organizationId];
        if ($ownerUserId !== null && $ownerUserId > 0) {
            $where .= ' AND owner_user_id = ?';
            $params[] = $ownerUserId;
        }
        $rows = Database::query(
            'SELECT status, COUNT(*) AS total FROM leads WHERE ' . $where . ' GROUP BY status',
            $params
        )->fetchAll();
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO leads (
                organization_id, name, company, email, phone, source, status, estimated_value, notes,
                owner_user_id, client_id, project_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['name'],
                $data['company'],
                $data['email'],
                $data['phone'],
                $data['source'],
                $data['status'],
                $data['estimated_value'],
                $data['notes'],
                $data['owner_user_id'],
                $data['client_id'] ?? null,
                $data['project_id'] ?? null,
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
            'UPDATE leads SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM leads WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    private static function selectSql(): string
    {
        return 'SELECT l.*, u.name AS owner_name, c.name AS client_name, p.name AS project_name
            FROM leads l
            LEFT JOIN users u ON u.id = l.owner_user_id
            LEFT JOIN clients c ON c.id = l.client_id
            LEFT JOIN projects p ON p.id = l.project_id';
    }
}
