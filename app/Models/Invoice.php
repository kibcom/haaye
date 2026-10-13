<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Invoice extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $client_id = 0;
    public ?int $project_id = null;
    public string $number = '';
    public string $issue_date = '';
    public string $due_date = '';
    public string $currency = 'USD';
    public string $subtotal = '0.00';
    public string $tax_rate = '0.00';
    public string $tax_amount = '0.00';
    public string $discount_amount = '0.00';
    public string $total = '0.00';
    public string $paid_amount = '0.00';
    public string $balance = '0.00';
    public string $status = 'draft';
    public ?string $notes = null;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public ?string $client_name = null;
    public ?string $project_name = null;
    public ?string $project_code = null;

    public static function table(): string
    {
        return 'invoices';
    }

    public function displayStatus(): string
    {
        if (in_array($this->status, ['sent', 'partial'], true) && (float) $this->balance > 0 && $this->due_date < date('Y-m-d')) {
            return 'overdue';
        }

        return $this->status;
    }

    public static function findInOrganization(int $id, int $organizationId): ?self
    {
        $row = Database::query(
            self::selectSql() . ' WHERE i.id = ? AND i.organization_id = ? LIMIT 1',
            [$id, $organizationId]
        )->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<self> */
    public static function listForOrganization(int $organizationId, ?int $onlyUserId, ?string $status, int $limit = 200): array
    {
        [$where, $params] = self::scope($organizationId, $onlyUserId);
        if ($status === 'overdue') {
            $where[] = "i.status IN ('sent','partial') AND i.balance > 0 AND i.due_date < CURDATE()";
        } elseif ($status !== null && $status !== '') {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY i.issue_date DESC, i.id DESC LIMIT ' . (int) $limit,
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return array<string, string|int> */
    public static function metrics(int $organizationId, ?int $onlyUserId): array
    {
        [$where, $params] = self::scope($organizationId, $onlyUserId);
        $clause = implode(' AND ', $where);
        $row = Database::query(
            "SELECT
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial') THEN i.balance ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial') AND i.due_date < CURDATE() AND i.balance > 0 THEN i.balance ELSE 0 END), 0) AS overdue_amount,
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial') AND i.due_date < CURDATE() AND i.balance > 0 THEN 1 ELSE 0 END), 0) AS overdue_count,
                COALESCE(SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial','paid') THEN i.total ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial','paid') AND i.issue_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN i.total ELSE 0 END), 0) AS month_revenue,
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial') THEN 1 ELSE 0 END), 0) AS open_count,
                COALESCE(SUM(CASE WHEN i.status IN ('sent','partial','paid') THEN i.paid_amount ELSE 0 END), 0) AS collected
             FROM invoices i
             WHERE {$clause}",
            $params
        )->fetch();

        return [
            'outstanding' => number_format((float) ($row['outstanding'] ?? 0), 2, '.', ''),
            'overdue_amount' => number_format((float) ($row['overdue_amount'] ?? 0), 2, '.', ''),
            'overdue_count' => (int) ($row['overdue_count'] ?? 0),
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'revenue' => number_format((float) ($row['revenue'] ?? 0), 2, '.', ''),
            'month_revenue' => number_format((float) ($row['month_revenue'] ?? 0), 2, '.', ''),
            'open_count' => (int) ($row['open_count'] ?? 0),
            'collected' => number_format((float) ($row['collected'] ?? 0), 2, '.', ''),
        ];
    }

    /**
     * Paid total, remaining balance, and payment rows for the given projects.
     *
     * @param list<int> $projectIds
     * @return array<int, array{paid: string, remaining: string, history: list<array{when: string, amount: string, detail: string}>}>
     */
    public static function paymentSnapshot(int $organizationId, array $projectIds, string $currency): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds))));
        $snapshot = [];
        foreach ($projectIds as $projectId) {
            $snapshot[$projectId] = ['paid' => '0.00', 'remaining' => '0.00', 'history' => []];
        }
        if ($snapshot === []) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($projectIds), '?'));
        $rows = Database::query(
            "SELECT project_id,
                    COALESCE(SUM(CASE WHEN status IN ('sent','partial','paid') THEN paid_amount ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('sent','partial') THEN balance ELSE 0 END), 0) AS remaining
             FROM invoices
             WHERE organization_id = ? AND project_id IN ($marks)
             GROUP BY project_id",
            array_merge([$organizationId], $projectIds)
        )->fetchAll();
        foreach ($rows as $row) {
            $projectId = (int) $row['project_id'];
            if (!isset($snapshot[$projectId])) {
                continue;
            }
            $snapshot[$projectId]['paid'] = number_format((float) $row['paid'], 2, '.', '');
            $snapshot[$projectId]['remaining'] = number_format((float) $row['remaining'], 2, '.', '');
        }

        $history = Database::query(
            "SELECT i.project_id, i.number, py.amount, py.paid_on, py.method, py.reference
             FROM payments py
             INNER JOIN invoices i ON i.id = py.invoice_id AND i.organization_id = py.organization_id
             WHERE py.organization_id = ? AND i.project_id IN ($marks)
             ORDER BY py.paid_on DESC, py.id DESC",
            array_merge([$organizationId], $projectIds)
        )->fetchAll();
        foreach ($history as $row) {
            $projectId = (int) $row['project_id'];
            if (!isset($snapshot[$projectId])) {
                continue;
            }
            $detail = (string) $row['number'] . ' · ' . ucfirst((string) $row['method']);
            if (!empty($row['reference'])) {
                $detail .= ' · ' . (string) $row['reference'];
            }
            $snapshot[$projectId]['history'][] = [
                'when' => format_date((string) $row['paid_on']),
                'amount' => money($currency, $row['amount']),
                'detail' => $detail,
            ];
        }

        return $snapshot;
    }

    /** @return array{revenue: string, outstanding: string} */
    public static function projectTotals(int $organizationId, int $projectId): array
    {
        $row = Database::query(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('sent','partial','paid') THEN total ELSE 0 END), 0) AS revenue,
                COALESCE(SUM(CASE WHEN status IN ('sent','partial') THEN balance ELSE 0 END), 0) AS outstanding
             FROM invoices
             WHERE organization_id = ? AND project_id = ?",
            [$organizationId, $projectId]
        )->fetch();

        return [
            'revenue' => number_format((float) ($row['revenue'] ?? 0), 2, '.', ''),
            'outstanding' => number_format((float) ($row['outstanding'] ?? 0), 2, '.', ''),
        ];
    }

    /** @return list<self> */
    public static function forProject(int $organizationId, int $projectId, int $limit = 8): array
    {
        $rows = Database::query(
            self::selectSql() . ' WHERE i.organization_id = ? AND i.project_id = ? ORDER BY i.issue_date DESC LIMIT ' . (int) $limit,
            [$organizationId, $projectId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function create(array $data): int
    {
        Database::query(
            'INSERT INTO invoices (
                organization_id, client_id, project_id, number, issue_date, due_date, currency,
                subtotal, tax_rate, tax_amount, discount_amount, total, paid_amount, balance,
                status, notes, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['organization_id'],
                $data['client_id'],
                $data['project_id'] ?? null,
                $data['number'],
                $data['issue_date'],
                $data['due_date'],
                $data['currency'],
                $data['subtotal'],
                $data['tax_rate'],
                $data['tax_amount'],
                $data['discount_amount'],
                $data['total'],
                $data['paid_amount'] ?? '0.00',
                $data['balance'],
                $data['status'] ?? 'draft',
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
                $data['created_at'],
                $data['updated_at'],
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function updateById(int $id, int $organizationId, array $data): void
    {
        $allowed = [
            'client_id', 'project_id', 'issue_date', 'due_date', 'subtotal', 'tax_rate', 'tax_amount',
            'discount_amount', 'total', 'paid_amount', 'balance', 'status', 'notes', 'updated_at',
        ];
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
        Database::query('UPDATE invoices SET ' . implode(', ', $sets) . ' WHERE id = ? AND organization_id = ?', $params);
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public static function scope(int $organizationId, ?int $onlyUserId): array
    {
        $where = ['i.organization_id = ?'];
        $params = [$organizationId];
        if ($onlyUserId !== null) {
            $where[] = 'EXISTS (
                SELECT 1 FROM projects p
                WHERE p.id = i.project_id AND p.organization_id = i.organization_id
                AND (p.manager_user_id = ? OR EXISTS (
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = p.id AND pm.user_id = ? AND pm.organization_id = p.organization_id
                ))
            )';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }

        return [$where, $params];
    }

    /** @return list<self> */
    public static function forClientAccount(int $organizationId, int $clientId): array
    {
        $rows = Database::query(
            self::selectSql() . " WHERE i.organization_id = ? AND i.client_id = ? AND i.status <> 'draft' ORDER BY i.issue_date DESC, i.id DESC",
            [$organizationId, $clientId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<self> */
    public static function openDueBy(int $organizationId, string $throughDate, ?int $onlyUserId = null, ?int $clientId = null): array
    {
        [$where, $params] = self::scope($organizationId, $clientId !== null ? null : $onlyUserId);
        $where[] = "i.status IN ('sent','partial')";
        $where[] = 'i.balance > 0';
        $where[] = 'i.due_date <= ?';
        $params[] = $throughDate;
        if ($clientId !== null && $clientId > 0) {
            $where[] = 'i.client_id = ?';
            $params[] = $clientId;
        }

        $rows = Database::query(
            self::selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY i.due_date ASC, i.id ASC LIMIT 20',
            $params
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function outstandingForClient(int $organizationId, int $clientId): string
    {
        $sum = Database::query(
            "SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE organization_id = ? AND client_id = ? AND status IN ('sent','partial')",
            [$organizationId, $clientId]
        )->fetchColumn();

        return number_format((float) $sum, 2, '.', '');
    }

    private static function selectSql(): string
    {
        return 'SELECT i.*, c.name AS client_name, p.name AS project_name, p.code AS project_code
            FROM invoices i
            INNER JOIN clients c ON c.id = i.client_id
            LEFT JOIN projects p ON p.id = i.project_id';
    }
}
