<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ClientContact extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $client_id = 0;
    public string $name = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $job_title = null;
    public int $is_primary = 0;
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function table(): string
    {
        return 'client_contacts';
    }

    public static function findInOrganization(int $id, int $clientId, int $organizationId): ?self
    {
        $row = Database::query(
            'SELECT * FROM client_contacts
             WHERE id = ? AND client_id = ? AND organization_id = ?
             LIMIT 1',
            [$id, $clientId, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forClient(int $clientId, int $organizationId): array
    {
        $rows = Database::query(
            'SELECT * FROM client_contacts
             WHERE client_id = ? AND organization_id = ?
             ORDER BY is_primary DESC, name ASC',
            [$clientId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function countForClient(int $clientId, int $organizationId): int
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM client_contacts WHERE client_id = ? AND organization_id = ?',
            [$clientId, $organizationId]
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO client_contacts (
                organization_id, client_id, name, email, phone, job_title, is_primary, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['client_id'],
                $data['name'],
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['job_title'] ?? null,
                $data['is_primary'] ?? 0,
                $data['notes'] ?? null,
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
            'UPDATE client_contacts SET ' . implode(', ', $fields) . ' WHERE id = ? AND organization_id = ?',
            $params
        );
    }

    public static function deleteInOrganization(int $id, int $clientId, int $organizationId): void
    {
        Database::query(
            'DELETE FROM client_contacts WHERE id = ? AND client_id = ? AND organization_id = ?',
            [$id, $clientId, $organizationId]
        );
    }

    public static function clearPrimary(int $clientId, int $organizationId): void
    {
        Database::query(
            'UPDATE client_contacts SET is_primary = 0 WHERE client_id = ? AND organization_id = ?',
            [$clientId, $organizationId]
        );
    }
}
