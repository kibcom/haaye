<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Organization;

final class BillingService
{
    public static function seedAll(): void
    {
        self::applySchema();
        $rows = Database::query('SELECT id FROM organizations')->fetchAll();
        foreach ($rows as $row) {
            $organization = Organization::find((int) $row['id']);
            if ($organization instanceof Organization) {
                self::seed($organization);
            }
        }
    }

    /**
     * @return array{
     *     code:string,
     *     name:string,
     *     price:string,
     *     priced:bool,
     *     currency:string,
     *     workspaces:?int,
     *     renews_on:?string,
     *     access:list<array{label:string, detail:string, included:bool, note:string}>,
     *     payments:list<array{paid_on:string, period_start:string, period_end:string, plan:string, amount:string, currency:string, method:string, reference:string, status:string}>
     * }
     */
    public function present(Organization $organization): array
    {
        self::applySchema();
        self::seed($organization);

        $code = $organization->subscription_plan;
        $names = FeatureService::planNames();
        $amount = self::monthlyAmount($code);
        $saved = FeatureService::saved($organization);
        $included = FeatureService::forPlan($code);
        $access = [];
        foreach (FeatureService::catalog() as $slug => $feature) {
            $onPlan = in_array($slug, $included, true);
            $note = '';
            if ($onPlan && $slug === 'time' && (int) $organization->show_time !== 1) {
                $note = 'Included on the plan. Timesheets are turned off for this company.';
            } elseif ($onPlan && $saved !== null && !in_array($slug, $saved, true)) {
                $note = 'Included on the plan, turned off for this company.';
            }
            $access[] = [
                'label' => $feature['label'],
                'detail' => $note !== '' ? $note : $feature['detail'],
                'included' => $onPlan,
                'note' => $note,
            ];
        }

        $payments = [];
        $renewsOn = null;
        $rows = Database::query(
            'SELECT plan_code, amount, currency, paid_on, period_start, period_end, status, method, reference
             FROM subscription_payments
             WHERE organization_id = ?
             ORDER BY paid_on DESC, id DESC',
            [$organization->id]
        )->fetchAll();
        foreach ($rows as $row) {
            if ($renewsOn === null) {
                $renewsOn = date('Y-m-d', strtotime((string) $row['period_end'] . ' +1 day'));
            }
            $payments[] = [
                'paid_on' => (string) $row['paid_on'],
                'period_start' => (string) $row['period_start'],
                'period_end' => (string) $row['period_end'],
                'plan' => (string) ($names[$row['plan_code']] ?? $row['plan_code']),
                'amount' => (string) $row['amount'],
                'currency' => (string) $row['currency'],
                'method' => self::methodLabel((string) $row['method']),
                'reference' => (string) $row['reference'],
                'status' => (string) $row['status'],
            ];
        }

        return [
            'code' => $code,
            'name' => (string) ($names[$code] ?? $code),
            'price' => $amount ?? '0.00',
            'priced' => $amount !== null,
            'currency' => strtoupper($organization->currency ?: 'ETB'),
            'workspaces' => FeatureService::workspaceLimit($code),
            'renews_on' => $renewsOn,
            'access' => $access,
            'payments' => $payments,
        ];
    }

    private static function applySchema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;

        Database::query(
            'CREATE TABLE IF NOT EXISTS subscription_payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                organization_id BIGINT UNSIGNED NOT NULL,
                plan_code VARCHAR(40) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL,
                paid_on DATE NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'paid\',
                method VARCHAR(30) NOT NULL DEFAULT \'bank\',
                reference VARCHAR(80) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_subscription_payments_ref (organization_id, reference),
                KEY idx_subscription_payments_org (organization_id, paid_on),
                CONSTRAINT fk_subscription_payments_org FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function seed(Organization $organization): void
    {
        $amount = self::monthlyAmount($organization->subscription_plan);
        if ($amount === null || (float) $amount <= 0 || $organization->id === null) {
            return;
        }

        $created = substr((string) $organization->created_at, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $created) !== 1) {
            $created = date('Y-m-d');
        }
        $first = date('Y-m-01', (int) strtotime($created));
        $code = $organization->company_code ?: ('ORG-' . $organization->id);
        $currency = strtoupper($organization->currency ?: 'ETB');
        $now = date('Y-m-d H:i:s');

        for ($i = 3; $i >= 0; $i--) {
            $start = date('Y-m-01', (int) strtotime(date('Y-m-01') . ' -' . $i . ' months'));
            if ($start < $first) {
                continue;
            }
            $reference = 'SUB-' . $code . '-' . date('Ym', (int) strtotime($start));
            $exists = Database::query(
                'SELECT id FROM subscription_payments WHERE organization_id = ? AND reference = ? LIMIT 1',
                [$organization->id, $reference]
            )->fetch();
            if ($exists) {
                continue;
            }

            Database::query(
                'INSERT INTO subscription_payments (
                    organization_id, plan_code, amount, currency, paid_on, period_start, period_end,
                    status, method, reference, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $organization->id,
                    $organization->subscription_plan,
                    $amount,
                    $currency,
                    $start,
                    $start,
                    date('Y-m-t', (int) strtotime($start)),
                    'paid',
                    $i % 2 === 0 ? 'card' : 'bank',
                    $reference,
                    $now,
                ]
            );
        }
    }

    public static function price(string $plan): ?string
    {
        return self::monthlyAmount($plan);
    }

    public static function methodName(string $method): string
    {
        return self::methodLabel($method);
    }

    private static function monthlyAmount(string $plan): ?string
    {
        $prices = [
            'FREE' => '0.00',
            'STARTER' => '1900.00',
            'PROFESSIONAL' => '4900.00',
            'BUSINESS' => '9900.00',
            'ENTERPRISE' => '19900.00',
        ];

        return $prices[$plan] ?? null;
    }

    private static function methodLabel(string $method): string
    {
        return match ($method) {
            'card' => 'Card',
            'bank' => 'Bank transfer',
            'mobile' => 'Mobile money',
            default => ucfirst($method),
        };
    }
}
