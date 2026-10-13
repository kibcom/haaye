<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Organization extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $slug = '';
    public ?string $logo = null;
    public ?string $industry = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $country = null;
    public string $currency = 'USD';
    public string $invoice_prefix = 'INV';
    public int $invoice_next_number = 1;
    public string $timezone = 'UTC';
    public ?string $tax_id = null;
    public string $default_tax_rate = '0.00';
    public ?string $tax_information = null;
    public ?string $website = null;
    public string $status = 'active';
    public string $subscription_plan = 'FREE';
    public ?string $company_code = null;
    public ?string $enabled_features = null;
    public int $show_guide = 1;
    public int $show_time = 0;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function table(): string
    {
        return 'organizations';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM organizations WHERE slug = ?';
        $params = [$slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return Database::query($sql . ' LIMIT 1', $params)->fetch() !== false;
    }

    public static function paginate(int $page, int $perPage, ?string $search = null, ?string $status = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR slug LIKE ? OR company_code LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== null && $status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $clause = implode(' AND ', $where);
        $total = (int) Database::query('SELECT COUNT(*) FROM organizations WHERE ' . $clause, $params)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::query(
            'SELECT * FROM organizations WHERE ' . $clause . ' ORDER BY name ASC LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
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

    public static function countAll(?string $status = null): int
    {
        if ($status === null) {
            return (int) Database::query('SELECT COUNT(*) FROM organizations')->fetchColumn();
        }

        return (int) Database::query('SELECT COUNT(*) FROM organizations WHERE status = ?', [$status])->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO organizations (
                name, slug, logo, industry, email, phone, address, city, country,
                currency, timezone, tax_id, tax_information, website, status,
                subscription_plan, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['slug'],
                $data['logo'] ?? null,
                $data['industry'] ?? null,
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['country'] ?? null,
                $data['currency'] ?? 'USD',
                $data['timezone'] ?? 'UTC',
                $data['tax_id'] ?? null,
                $data['tax_information'] ?? null,
                $data['website'] ?? null,
                $data['status'] ?? 'active',
                $data['subscription_plan'] ?? 'FREE',
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        $id = (int) Database::lastInsertId();
        Database::query(
            'UPDATE organizations SET company_code = ? WHERE id = ?',
            [self::formatCompanyCode($id), $id]
        );

        return $id;
    }

    public static function formatCompanyCode(int $id): string
    {
        return 'CMP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public static function ensureCompanyCodes(): void
    {
        Database::query('ALTER TABLE organizations ADD COLUMN IF NOT EXISTS company_code VARCHAR(20) NULL');
        $rows = Database::query(
            "SELECT id FROM organizations WHERE company_code IS NULL OR company_code = ''"
        )->fetchAll();
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            Database::query(
                'UPDATE organizations SET company_code = ? WHERE id = ?',
                [self::formatCompanyCode($id), $id]
            );
        }

        $index = Database::query(
            "SHOW INDEX FROM organizations WHERE Key_name = 'uq_organizations_company_code'"
        )->fetch();
        if ($index === false) {
            Database::query('CREATE UNIQUE INDEX uq_organizations_company_code ON organizations (company_code)');
        }
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
        Database::query('UPDATE organizations SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public static function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;
        $i = 2;
        while (self::slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
