<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User extends Model
{
    public ?int $id = null;
    public ?int $organization_id = null;
    public ?int $client_id = null;
    public int $role_id = 0;
    public int $custom_permissions = 0;
    public string $name = '';
    public string $email = '';
    public ?string $phone = null;
    public ?string $profile_photo = null;
    public ?string $job_title = null;
    public ?string $employee_code = null;
    public string $status = 'active';
    public ?string $joining_date = null;
    public string $password_hash = '';
    public ?string $last_login_at = null;
    public ?string $last_login_ip = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $role_name = null;
    public ?string $role_slug = null;
    public ?string $organization_name = null;

    public static function table(): string
    {
        return 'users';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function findByEmail(string $email): ?self
    {
        $accounts = self::findAllByEmail($email);
        return $accounts[0] ?? null;
    }

    /** @return list<self> */
    public static function findAllByEmail(string $email): array
    {
        $rows = Database::query(
            'SELECT * FROM users WHERE email = ? ORDER BY last_login_at DESC, id ASC',
            [strtolower($email)]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * Companies this email can open. The same person can have one account in each company.
     *
     * @return list<array{user_id: int, organization_id: int, name: string, company_code: string, logo: string}>
     */
    public static function workspacesForEmail(string $email): array
    {
        self::ensureMultiAccountEmail();
        $rows = Database::query(
            'SELECT u.id AS user_id, u.organization_id, o.name, o.company_code, o.logo
             FROM users u
             INNER JOIN organizations o ON o.id = u.organization_id
             WHERE u.email = ? AND u.status = ? AND o.status = ?
             ORDER BY o.name ASC',
            [strtolower($email), 'active', 'active']
        )->fetchAll();

        $workspaces = [];
        foreach ($rows as $row) {
            $workspaces[] = [
                'user_id' => (int) $row['user_id'],
                'organization_id' => (int) $row['organization_id'],
                'name' => (string) $row['name'],
                'company_code' => (string) ($row['company_code'] ?? ''),
                'logo' => (string) ($row['logo'] ?? ''),
            ];
        }

        return $workspaces;
    }

    public static function emailExistsInOrganization(string $email, ?int $organizationId, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = ? AND organization_id <=> ?';
        $params = [strtolower($email), $organizationId];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        return Database::query($sql, $params)->fetch() !== false;
    }

    public static function ensureMultiAccountEmail(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;

        $legacy = Database::query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_email'")->fetch();
        if ($legacy) {
            Database::query('ALTER TABLE users DROP INDEX uq_users_email');
        }

        $scoped = Database::query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_org_email'")->fetch();
        if (!$scoped) {
            Database::query('CREATE UNIQUE INDEX uq_users_org_email ON users (organization_id, email)');
        }
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $statement = Database::query(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.organization_id = ?
             LIMIT 1',
            [$id, $organizationId]
        );

        $row = $statement->fetch();
        return $row ? self::hydrate($row) : null;
    }

    public static function paginateForOrganization(int $organizationId, int $page, int $perPage, ?string $search = null, ?string $status = null): array
    {
        $where = ['u.organization_id = ?'];
        $params = [$organizationId];

        if ($search !== null && $search !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.employee_code LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== null && $status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::query('SELECT COUNT(*) FROM users u WHERE ' . $clause, $params)->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT u.*, r.name AS role_name, r.slug AS role_slug
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE ' . $clause . '
                ORDER BY u.name ASC
                LIMIT ' . (int) $perPage . ' OFFSET ' . $offset;

        $rows = Database::query($sql, $params)->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'items' => array_map([self::class, 'hydrate'], $rows),
        ];
    }

    public static function countByOrganization(int $organizationId, ?string $status = null): int
    {
        if ($status === null) {
            return (int) Database::query('SELECT COUNT(*) FROM users WHERE organization_id = ?', [$organizationId])->fetchColumn();
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM users WHERE organization_id = ? AND status = ?',
            [$organizationId, $status]
        )->fetchColumn();
    }

    public static function countOwners(int $organizationId, ?int $exceptId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.organization_id = ? AND r.slug = ? AND u.status = ?';
        $params = [$organizationId, 'owner', 'active'];

        if ($exceptId !== null) {
            $sql .= ' AND u.id <> ?';
            $params[] = $exceptId;
        }

        return (int) Database::query($sql, $params)->fetchColumn();
    }

    /** @return list<self> */
    public static function listAssignable(int $organizationId): array
    {
        $rows = Database::query(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.organization_id = ? AND u.status = 'active' AND r.slug <> 'client'
             ORDER BY u.name ASC",
            [$organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function paginatePlatformUsers(int $page, int $perPage): array
    {
        $total = (int) Database::query('SELECT COUNT(*) FROM users WHERE organization_id IS NULL')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.organization_id IS NULL
             ORDER BY u.name ASC
             LIMIT ' . (int) $perPage . ' OFFSET ' . $offset
        )->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'items' => array_map([self::class, 'hydrate'], $rows),
        ];
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO users (
                organization_id, role_id, name, email, phone, profile_photo, job_title,
                employee_code, status, joining_date, password_hash, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['role_id'],
                $data['name'],
                strtolower((string) $data['email']),
                $data['phone'] ?? null,
                $data['profile_photo'] ?? null,
                $data['job_title'] ?? null,
                $data['employee_code'] ?? null,
                $data['status'] ?? 'active',
                $data['joining_date'] ?? null,
                $data['password_hash'],
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach ($data as $column => $value) {
            $fields[] = $column . ' = ?';
            $params[] = $value;
        }

        $params[] = $id;
        Database::query('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public function role(): ?Role
    {
        return Role::find($this->role_id);
    }

    public function organization(): ?Organization
    {
        if ($this->organization_id === null) {
            return null;
        }

        return Organization::find($this->organization_id);
    }
}
