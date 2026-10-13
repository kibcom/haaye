<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Client extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public string $name = '';
    public ?string $contact_name = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $country = null;
    public ?string $tax_id = null;
    public ?string $tax_information = null;
    public ?string $website = null;
    public ?string $industry = null;
    public string $status = 'lead';
    public ?string $notes = null;
    public ?int $assigned_user_id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $assigned_user_name = null;
    public ?int $contact_count = null;
    public ?int $project_count = null;

    public static function table(): string
    {
        return 'clients';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT c.*, u.name AS assigned_user_name
             FROM clients c
             LEFT JOIN users u ON u.id = c.assigned_user_id AND u.organization_id = c.organization_id
             WHERE c.id = ? AND c.organization_id = ?
             LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    public static function paginateForOrganization(
        int $organizationId,
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?int $assignedUserId = null
    ): array {
        $where = ['c.organization_id = ?'];
        $params = [$organizationId];

        if ($search !== null && $search !== '') {
            $where[] = '(c.name LIKE ? OR c.email LIKE ? OR c.contact_name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== null && $status !== '') {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }

        if ($assignedUserId !== null && $assignedUserId > 0) {
            $where[] = 'c.assigned_user_id = ?';
            $params[] = $assignedUserId;
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::query('SELECT COUNT(*) FROM clients c WHERE ' . $clause, $params)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::query(
            'SELECT c.*, u.name AS assigned_user_name,
                    (SELECT COUNT(*) FROM client_contacts cc WHERE cc.client_id = c.id AND cc.organization_id = c.organization_id) AS contact_count,
                    (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id AND p.organization_id = c.organization_id) AS project_count
             FROM clients c
             LEFT JOIN users u ON u.id = c.assigned_user_id AND u.organization_id = c.organization_id
             WHERE ' . $clause . '
             ORDER BY c.name ASC
             LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'items' => array_map([self::class, 'hydrate'], $rows),
        ];
    }

    /** @return list<self> */
    public static function optionsForOrganization(int $organizationId): array
    {
        $rows = Database::query(
            "SELECT id, organization_id, name, status FROM clients
             WHERE organization_id = ? AND status <> 'archived'
             ORDER BY name ASC",
            [$organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function countByOrganization(int $organizationId, ?string $status = null): int
    {
        if ($status === null) {
            return (int) Database::query(
                'SELECT COUNT(*) FROM clients WHERE organization_id = ?',
                [$organizationId]
            )->fetchColumn();
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM clients WHERE organization_id = ? AND status = ?',
            [$organizationId, $status]
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO clients (
                organization_id, name, contact_name, email, phone, address, city, country,
                tax_id, tax_information, website, industry, status, notes, assigned_user_id,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['name'],
                $data['contact_name'] ?? null,
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['country'] ?? null,
                $data['tax_id'] ?? null,
                $data['tax_information'] ?? null,
                $data['website'] ?? null,
                $data['industry'] ?? null,
                $data['status'] ?? 'lead',
                $data['notes'] ?? null,
                $data['assigned_user_id'] ?? null,
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
            'UPDATE clients SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }
}
