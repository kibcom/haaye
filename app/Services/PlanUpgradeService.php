<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\FileUploader;
use App\Models\Organization;
use App\Models\User;

final class PlanUpgradeService
{
    /** @var list<string> */
    public const METHODS = ['bank', 'card', 'mobile'];

    public static function pendingCount(): int
    {
        self::applySchema();

        return (int) Database::query(
            "SELECT COUNT(*) FROM plan_upgrade_requests WHERE status = 'pending'"
        )->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public static function pending(int $organizationId): ?array
    {
        self::applySchema();
        $row = Database::query(
            "SELECT * FROM plan_upgrade_requests WHERE organization_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$organizationId]
        )->fetch();

        return $row ? self::present($row) : null;
    }

    /** @return array<string, mixed>|null */
    public static function latest(int $organizationId): ?array
    {
        self::applySchema();
        $row = Database::query(
            'SELECT * FROM plan_upgrade_requests WHERE organization_id = ? ORDER BY id DESC LIMIT 1',
            [$organizationId]
        )->fetch();

        return $row ? self::present($row) : null;
    }

    /**
     * @return list<array{code:string, name:string, price:?string, workspaces:?int, features:int}>
     */
    public static function choices(Organization $organization): array
    {
        $choices = [];
        foreach (FeatureService::planNames() as $code => $name) {
            if ($code === $organization->subscription_plan) {
                continue;
            }
            $choices[] = self::describe($code);
        }

        return $choices;
    }

    /**
     * @return array{code:string, name:string, price:?string, workspaces:?int, features:int}
     */
    public static function describe(string $code): array
    {
        $names = FeatureService::planNames();
        $limits = FeatureService::workspaceLimits();

        return [
            'code' => $code,
            'name' => (string) ($names[$code] ?? $code),
            'price' => BillingService::price($code),
            'workspaces' => array_key_exists($code, $limits) ? $limits[$code] : FeatureService::workspaceLimit($code),
            'features' => count(FeatureService::forPlan($code)),
        ];
    }

    public static function submit(Organization $organization, User $user, string $plan, string $method, string $reference, string $note, ?array $file): int
    {
        self::applySchema();
        if (self::pending((int) $organization->id) !== null) {
            throw new \RuntimeException('A plan request is already waiting for approval.');
        }

        $names = FeatureService::planNames();
        if (!isset($names[$plan]) || $plan === $organization->subscription_plan) {
            throw new \RuntimeException('Choose a different plan.');
        }
        if (!in_array($method, self::METHODS, true)) {
            throw new \RuntimeException('Choose a payment method.');
        }

        $screenshot = FileUploader::image($file, 'plan-payments');
        if ($screenshot === null) {
            throw new \RuntimeException('Upload a screenshot of the payment.');
        }

        $price = BillingService::price($plan);
        $now = date('Y-m-d H:i:s');
        Database::query(
            'INSERT INTO plan_upgrade_requests (
                organization_id, requested_by, current_plan, requested_plan, amount, currency,
                method, reference, note, screenshot, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $organization->id,
                $user->id,
                $organization->subscription_plan,
                $plan,
                $price ?? '0.00',
                strtoupper($organization->currency ?: 'ETB'),
                $method,
                $reference !== '' ? substr($reference, 0, 80) : null,
                $note !== '' ? substr($note, 0, 500) : null,
                $screenshot,
                'pending',
                $now,
            ]
        );

        return (int) Database::lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        self::applySchema();
        $rows = Database::query(
            'SELECT r.*, o.name AS organization_name, o.company_code, u.name AS requester_name
             FROM plan_upgrade_requests r
             INNER JOIN organizations o ON o.id = r.organization_id
             LEFT JOIN users u ON u.id = r.requested_by
             ORDER BY FIELD(r.status, \'pending\', \'approved\', \'rejected\'), r.id DESC'
        )->fetchAll();

        return array_map([self::class, 'present'], $rows);
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        self::applySchema();
        $row = Database::query(
            'SELECT r.*, o.name AS organization_name, o.company_code, u.name AS requester_name,
                    reviewer.name AS reviewer_name
             FROM plan_upgrade_requests r
             INNER JOIN organizations o ON o.id = r.organization_id
             LEFT JOIN users u ON u.id = r.requested_by
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by
             WHERE r.id = ?
             LIMIT 1',
            [$id]
        )->fetch();

        return $row ? self::present($row) : null;
    }

    public static function approve(int $id, User $reviewer, string $note): void
    {
        $request = self::lockPending($id);
        $plan = (string) $request['requested_plan'];
        $names = FeatureService::planNames();
        if (!isset($names[$plan])) {
            throw new \RuntimeException('That plan is no longer available.');
        }

        $now = date('Y-m-d H:i:s');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Organization::updateById((int) $request['organization_id'], [
                'subscription_plan' => $plan,
                'enabled_features' => FeatureService::encode(FeatureService::forPlan($plan)),
                'updated_at' => $now,
            ]);

            $amount = (string) $request['amount'];
            if ((float) $amount > 0) {
                $reference = 'UPG-' . $id;
                $start = date('Y-m-01');
                Database::query(
                    'INSERT INTO subscription_payments (
                        organization_id, plan_code, amount, currency, paid_on, period_start, period_end,
                        status, method, reference, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $request['organization_id'],
                        $plan,
                        $amount,
                        $request['currency'],
                        date('Y-m-d'),
                        $start,
                        date('Y-m-t'),
                        'paid',
                        $request['method'],
                        $reference,
                        $now,
                    ]
                );
            }

            self::review($id, 'approved', $reviewer, $note, $now);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function reject(int $id, User $reviewer, string $note): void
    {
        self::lockPending($id);
        self::review($id, 'rejected', $reviewer, $note, date('Y-m-d H:i:s'));
    }

    /** @param array<string, mixed> $row */
    private static function present(array $row): array
    {
        $names = FeatureService::planNames();
        $row['current_plan_name'] = (string) ($names[$row['current_plan']] ?? $row['current_plan']);
        $row['requested_plan_name'] = (string) ($names[$row['requested_plan']] ?? $row['requested_plan']);
        $row['method_label'] = BillingService::methodName((string) $row['method']);

        return $row;
    }

    /** @return array<string, mixed> */
    private static function lockPending(int $id): array
    {
        self::applySchema();
        $row = Database::query(
            'SELECT * FROM plan_upgrade_requests WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();
        if (!$row || (string) $row['status'] !== 'pending') {
            throw new \RuntimeException('This request has already been reviewed.');
        }

        return $row;
    }

    private static function review(int $id, string $status, User $reviewer, string $note, string $now): void
    {
        Database::query(
            'UPDATE plan_upgrade_requests
             SET status = ?, reviewed_by = ?, reviewed_at = ?, review_note = ?
             WHERE id = ? AND status = ?',
            [$status, $reviewer->id, $now, $note !== '' ? substr($note, 0, 500) : null, $id, 'pending']
        );
    }

    private static function applySchema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;

        Database::query(
            'CREATE TABLE IF NOT EXISTS plan_upgrade_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                organization_id BIGINT UNSIGNED NOT NULL,
                requested_by BIGINT UNSIGNED NULL,
                current_plan VARCHAR(40) NOT NULL,
                requested_plan VARCHAR(40) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL,
                method VARCHAR(30) NOT NULL,
                reference VARCHAR(80) NULL,
                note VARCHAR(500) NULL,
                screenshot VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                review_note VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_plan_requests_org (organization_id, status),
                CONSTRAINT fk_plan_requests_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE,
                CONSTRAINT fk_plan_requests_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_plan_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
