<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Payment extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $invoice_id = 0;
    public int $client_id = 0;
    public string $amount = '0.00';
    public string $paid_on = '';
    public string $method = 'bank';
    public ?string $reference = null;
    public ?string $notes = null;
    public ?int $recorded_by = null;
    public ?string $created_at = null;

    public ?string $recorder_name = null;
    public ?string $invoice_number = null;
    public ?string $invoice_currency = null;

    public static function table(): string
    {
        return 'payments';
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE py.id = ? AND py.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function forInvoice(int $invoiceId, int $organizationId): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE py.invoice_id = ? AND py.organization_id = ? ORDER BY py.paid_on ASC, py.id ASC',
            [$invoiceId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<array{month: string, amount: string}> */
    public static function monthlyTotals(int $organizationId, ?int $onlyUserId, string $fromDate): array
    {
        [$where, $params] = Invoice::scope($organizationId, $onlyUserId);
        $where[] = 'py.paid_on >= ?';
        $params[] = $fromDate;
        $rows = Database::query(
            'SELECT DATE_FORMAT(py.paid_on, \'%Y-%m\') AS month_key, COALESCE(SUM(py.amount), 0) AS amount
             FROM payments py
             INNER JOIN invoices i ON i.id = py.invoice_id AND i.organization_id = py.organization_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY month_key
             ORDER BY month_key ASC',
            $params
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['month_key']] = number_format((float) $row['amount'], 2, '.', '');
        }

        $trend = [];
        $cursor = new \DateTimeImmutable(substr($fromDate, 0, 7) . '-01');
        $end = new \DateTimeImmutable(date('Y-m-01'));
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m');
            $trend[] = [
                'month' => $cursor->format('M Y'),
                'amount' => $map[$key] ?? '0.00',
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return $trend;
    }

    public static function sumForInvoice(int $invoiceId, int $organizationId): string
    {
        $sum = Database::query(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND organization_id = ?',
            [$invoiceId, $organizationId]
        )->fetchColumn();

        return number_format((float) $sum, 2, '.', '');
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO payments (
                organization_id, invoice_id, client_id, amount, paid_on, method, reference, notes, recorded_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['invoice_id'],
                $data['client_id'],
                $data['amount'],
                $data['paid_on'],
                $data['method'],
                $data['reference'] ?? null,
                $data['notes'] ?? null,
                $data['recorded_by'] ?? null,
                $data['created_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function deleteById(int $id, int $organizationId): void
    {
        Database::query('DELETE FROM payments WHERE id = ? AND organization_id = ?', [$id, $organizationId]);
    }

    /** @return list<self> */
    public static function forClientAccount(int $organizationId, int $clientId): array
    {
        $rows = Database::query(
            self::selectSql() . " WHERE py.organization_id = ? AND i.client_id = ? AND i.status <> 'draft' ORDER BY py.paid_on DESC, py.id DESC",
            [$organizationId, $clientId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    private static function selectSql(): string
    {
        return 'SELECT py.*, u.name AS recorder_name, i.number AS invoice_number, i.currency AS invoice_currency
            FROM payments py
            INNER JOIN invoices i ON i.id = py.invoice_id
            LEFT JOIN users u ON u.id = py.recorded_by';
    }
}
