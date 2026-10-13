<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Models\Organization;

final class FeatureService
{
    /** @var array<string, array{label:string, detail:string}>|null */
    private static ?array $catalog = null;

    private static bool $loaded = false;

    /** @var list<string>|null */
    private static ?array $current = null;

    /** @var array<string, array{name:string, features:list<string>, sort:int, limit:?int}>|null */
    private static ?array $records = null;

    /** @return array<string, array{label:string, detail:string}> */
    public static function catalog(): array
    {
        if (self::$catalog === null) {
            self::$catalog = [
                'leads' => ['label' => 'Leads', 'detail' => 'Inquiries before they become clients'],
                'work_flow' => ['label' => 'Workflow', 'detail' => 'Production roles, project tasks, and monthly content grids per company'],
                'clients' => ['label' => 'Clients', 'detail' => 'Companies and contacts'],
                'projects' => ['label' => 'Projects', 'detail' => 'Delivery work, members, and milestones'],
                'bookings' => ['label' => 'Bookings', 'detail' => 'Meetings and reserved time on a project'],
                'tasks' => ['label' => 'Tasks', 'detail' => 'Assignments, submissions, and approvals'],
                'pipeline' => ['label' => 'Pipeline workflows', 'detail' => 'Multi-stage production templates and project runs'],
                'time' => ['label' => 'Timesheets', 'detail' => 'Timers, logs, and workload'],
                'expenses' => ['label' => 'Expenses', 'detail' => 'Costs submitted for approval'],
                'deliverables' => ['label' => 'Deliverables', 'detail' => 'Files shared for review'],
                'approvals' => ['label' => 'Approvals', 'detail' => 'Internal and client decisions'],
                'workflows' => ['label' => 'Status workflows', 'detail' => 'Allowed status changes'],
                'finance' => ['label' => 'Finance', 'detail' => 'Outstanding balances and profit'],
                'invoices' => ['label' => 'Invoices and payments', 'detail' => 'Billing and recorded payments'],
                'portal' => ['label' => 'Client portal', 'detail' => 'What the client can sign in and see'],
            ];
        }

        return self::$catalog;
    }

    /** @return array<string, list<string>> */
    public static function plans(): array
    {
        $plans = [];
        foreach (self::records() as $code => $record) {
            $plans[$code] = $record['features'];
        }

        return $plans;
    }

    /** @return array<string, ?int> */
    public static function workspaceLimits(): array
    {
        $limits = [];
        foreach (self::records() as $code => $record) {
            $limits[$code] = $record['limit'];
        }

        return $limits;
    }

    /** @return array<string, string> */
    public static function planNames(): array
    {
        $names = [];
        foreach (self::records() as $code => $record) {
            $names[$code] = $record['name'];
        }

        return $names;
    }

    public static function planRule(): string
    {
        return 'required|in:' . implode(',', array_keys(self::plans()));
    }

    /** @return list<string> */
    public static function forPlan(string $plan): array
    {
        $plans = self::plans();
        if (isset($plans[$plan])) {
            return $plans[$plan];
        }

        if (isset($plans['FREE'])) {
            return $plans['FREE'];
        }

        $first = reset($plans);

        return is_array($first) ? $first : [];
    }

    /** @return array<string, int> */
    public static function usageCounts(): array
    {
        $counts = [];
        $rows = Database::query(
            'SELECT subscription_plan, COUNT(*) AS total FROM organizations GROUP BY subscription_plan'
        )->fetchAll();
        foreach ($rows as $row) {
            $counts[(string) $row['subscription_plan']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @param list<array{code:string, name:string, features:mixed, limit?:mixed}> $rows
     */
    public static function save(array $rows): void
    {
        $known = self::records();
        $now = date('Y-m-d H:i:s');
        $sort = 0;
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            if (!isset($known[$code])) {
                continue;
            }
            Database::query(
                'UPDATE subscription_plans SET name = ?, features = ?, workspace_limit = ?, sort_order = ?, updated_at = ? WHERE code = ?',
                [
                    substr(trim((string) $row['name']), 0, 80),
                    self::encode(is_array($row['features']) ? $row['features'] : []),
                    self::normalizeLimit($row['limit'] ?? null),
                    $sort,
                    $now,
                    $code,
                ]
            );
            $sort++;
        }
        self::$records = null;
    }

    /** Null means the plan does not cap how many workspaces one account can open. */
    public static function workspaceLimit(string $plan): ?int
    {
        $records = self::records();
        if (isset($records[$plan]) && array_key_exists('limit', $records[$plan])) {
            return $records[$plan]['limit'];
        }
        $defaults = self::builtinLimits();
        if (array_key_exists($plan, $defaults)) {
            return $defaults[$plan];
        }

        return 1;
    }

    /**
     * The account may use the highest workspace allowance among the plans it already has.
     *
     * @return array{used:int, limit:?int, plan:string, can_create:bool}
     */
    public static function workspaceAllowance(string $email): array
    {
        $rows = Database::query(
            'SELECT o.subscription_plan
             FROM users u
             INNER JOIN organizations o ON o.id = u.organization_id
             WHERE u.email = ? AND u.status = ? AND o.status = ?',
            [strtolower($email), 'active', 'active']
        )->fetchAll();

        $used = count($rows);
        $limit = 1;
        $unlimited = false;
        $plan = 'FREE';
        $best = -1;
        foreach ($rows as $row) {
            $code = (string) $row['subscription_plan'];
            $planLimit = self::workspaceLimit($code);
            $sort = self::records()[$code]['sort'] ?? 0;
            if ($planLimit === null) {
                $unlimited = true;
                if ($sort >= $best) {
                    $plan = $code;
                    $best = $sort;
                }
                continue;
            }
            if ($planLimit >= $limit && $sort >= $best) {
                $limit = $planLimit;
                $plan = $code;
                $best = $sort;
            } elseif ($planLimit > $limit) {
                $limit = $planLimit;
            }
        }

        return [
            'used' => $used,
            'limit' => $unlimited ? null : $limit,
            'plan' => $plan,
            'can_create' => $unlimited || $used < $limit,
        ];
    }

    public static function add(string $code, string $name, string $copyFrom, mixed $workspaceLimit = null): ?string
    {
        $code = strtoupper(trim($code));
        $name = trim($name);
        if (!preg_match('/^[A-Z][A-Z0-9_]{0,39}$/', $code)) {
            return 'Use a code such as GROWTH. Letters, numbers, and underscores only.';
        }
        if (isset(self::records()[$code])) {
            return 'That plan code already exists.';
        }
        if ($name === '' || strlen($name) > 80) {
            return 'Enter a plan name of 80 characters or fewer.';
        }

        $now = date('Y-m-d H:i:s');
        Database::query(
            'INSERT INTO subscription_plans (code, name, features, workspace_limit, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $code,
                $name,
                self::encode(self::forPlan($copyFrom)),
                ($workspaceLimit === null || $workspaceLimit === '') ? self::workspaceLimit($copyFrom) : self::normalizeLimit($workspaceLimit),
                count(self::records()),
                $now,
                $now,
            ]
        );
        self::$records = null;

        return null;
    }

    public static function remove(string $code): ?string
    {
        $code = strtoupper(trim($code));
        if ($code === 'FREE') {
            return 'The Free plan stays available as the fallback.';
        }
        if (!isset(self::records()[$code])) {
            return 'That plan was not found.';
        }

        $used = (int) Database::query(
            'SELECT COUNT(*) FROM organizations WHERE subscription_plan = ?',
            [$code]
        )->fetchColumn();
        if ($used > 0) {
            return 'Move the companies on this plan before removing it.';
        }

        Database::query('DELETE FROM subscription_plans WHERE code = ?', [$code]);
        self::$records = null;

        return null;
    }

    /**
     * Null means the company has not been limited and every feature stays on.
     *
     * @return list<string>|null
     */
    public static function saved(Organization $organization): ?array
    {
        $raw = $organization->enabled_features;
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return self::sanitize($decoded);
    }

    /** @param mixed $values */
    public static function sanitize($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $allowed = array_keys(self::catalog());
        $clean = [];
        foreach ($values as $value) {
            $slug = (string) $value;
            if (in_array($slug, $allowed, true) && !in_array($slug, $clean, true)) {
                $clean[] = $slug;
            }
        }

        return $clean;
    }

    public static function encode(array $slugs): string
    {
        return json_encode(self::sanitize($slugs)) ?: '[]';
    }

    public static function allows(string $slug): bool
    {
        if (!isset(self::catalog()[$slug])) {
            return true;
        }

        self::load();
        if ($slug === 'time' && !self::timeDisplayOn()) {
            return false;
        }

        if (self::$current === null) {
            return true;
        }

        return in_array($slug, self::$current, true);
    }

    /**
     * Features this company can actually see. Timesheets stay off until the company switch is on.
     *
     * @return list<string>|null
     */
    public static function displayed(?Organization $organization): ?array
    {
        if (!$organization instanceof Organization) {
            return null;
        }

        $saved = self::saved($organization);
        if ((int) $organization->show_time === 1) {
            return $saved;
        }

        $features = $saved ?? array_keys(self::catalog());

        return array_values(array_filter(
            $features,
            static fn (string $slug): bool => $slug !== 'time'
        ));
    }

    private static function timeDisplayOn(): bool
    {
        $user = Auth::user();
        if ($user === null || $user->organization_id === null) {
            return true;
        }

        $organization = $user->organization();

        return $organization instanceof Organization && (int) $organization->show_time === 1;
    }

    public static function guard(string $path): void
    {
        $feature = self::featureForPath($path);
        if ($feature === null || self::allows($feature)) {
            return;
        }

        Response::forbidden();
    }

    public static function applySchema(): void
    {
        Database::query('ALTER TABLE organizations ADD COLUMN IF NOT EXISTS enabled_features TEXT NULL');
        Database::query('ALTER TABLE organizations ADD COLUMN IF NOT EXISTS show_guide TINYINT(1) NOT NULL DEFAULT 1');
        Database::query('ALTER TABLE organizations ADD COLUMN IF NOT EXISTS show_time TINYINT(1) NOT NULL DEFAULT 0');
        Organization::ensureCompanyCodes();
        \App\Services\AuthorizationService::applySchema();
        Database::query(
            'CREATE TABLE IF NOT EXISTS subscription_plans (
                code VARCHAR(40) NOT NULL,
                name VARCHAR(80) NOT NULL,
                features TEXT NOT NULL,
                workspace_limit INT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::ensureWorkspaceLimitColumn();
        self::seedDefaults();
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $role = Database::query("SELECT id FROM roles WHERE slug = 'super_admin' LIMIT 1")->fetch();
        if (!$role) {
            return;
        }

        foreach ([
            ['View plans', 'platform.plans.view'],
            ['Update plans', 'platform.plans.update'],
        ] as $permission) {
            $row = Database::query('SELECT id FROM permissions WHERE slug = ? LIMIT 1', [$permission[1]])->fetch();
            if ($row) {
                $permissionId = (int) $row['id'];
            } else {
                Database::query(
                    'INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)',
                    [$permission[0], $permission[1], 'Platform', $now]
                );
                $permissionId = (int) Database::lastInsertId();
            }

            $exists = Database::query(
                'SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1',
                [(int) $role['id'], $permissionId]
            )->fetch();
            if (!$exists) {
                Database::query(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                    [(int) $role['id'], $permissionId]
                );
            }
        }
    }

    public static function featureForPath(string $path): ?string
    {
        $map = [
            '/leads' => 'leads',
            '/work-flow' => 'work_flow',
            '/clients' => 'clients',
            '/bookings' => 'bookings',
            '/projects' => 'projects',
            '/tasks' => 'tasks',
            '/workspace' => 'tasks',
            '/my-work' => 'tasks',
            '/pipeline' => 'pipeline',
            '/calendar' => 'tasks',
            '/time' => 'time',
            '/expenses' => 'expenses',
            '/deliverables' => 'deliverables',
            '/approvals' => 'approvals',
            '/workflows' => 'workflows',
            '/finance' => 'finance',
            '/invoices' => 'invoices',
            '/payments' => 'invoices',
            '/portal' => 'portal',
        ];

        foreach ($map as $prefix => $feature) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $feature;
            }
        }

        return null;
    }

    /** @return array<string, ?int> */
    private static function builtinLimits(): array
    {
        return [
            'FREE' => 1,
            'STARTER' => 2,
            'PROFESSIONAL' => 5,
            'BUSINESS' => 10,
            'ENTERPRISE' => null,
        ];
    }

    /** @return array<string, array{name:string, features:list<string>, sort:int, limit:?int}> */
    private static function builtin(): array
    {
        $all = array_keys(self::catalog());
        $limits = self::builtinLimits();
        $definitions = [
            ['FREE', 'Free', ['leads', 'work_flow', 'clients', 'projects', 'bookings', 'tasks', 'pipeline']],
            ['STARTER', 'Starter', ['leads', 'work_flow', 'clients', 'projects', 'bookings', 'tasks', 'pipeline', 'time', 'expenses']],
            ['PROFESSIONAL', 'Professional', ['leads', 'work_flow', 'clients', 'projects', 'bookings', 'tasks', 'pipeline', 'time', 'expenses', 'deliverables', 'approvals', 'workflows']],
            ['BUSINESS', 'Business', ['leads', 'work_flow', 'clients', 'projects', 'bookings', 'tasks', 'pipeline', 'time', 'expenses', 'deliverables', 'approvals', 'workflows', 'finance', 'invoices', 'portal']],
            ['ENTERPRISE', 'Enterprise', $all],
        ];
        $records = [];
        foreach ($definitions as $index => $definition) {
            $records[$definition[0]] = [
                'name' => $definition[1],
                'features' => $definition[2],
                'sort' => $index,
                'limit' => $limits[$definition[0]] ?? 1,
            ];
        }

        return $records;
    }

    private static function ensureWorkspaceLimitColumn(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;

        Database::query('ALTER TABLE subscription_plans ADD COLUMN IF NOT EXISTS workspace_limit INT NULL');
        foreach (self::builtinLimits() as $code => $limit) {
            if ($limit === null) {
                continue;
            }
            Database::query(
                'UPDATE subscription_plans SET workspace_limit = ? WHERE code = ? AND workspace_limit IS NULL',
                [$limit, $code]
            );
        }
    }

    private static function normalizeLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $limit = (int) $value;
        if ($limit < 1) {
            return null;
        }

        return min($limit, 999);
    }

    /** @return array<string, array{name:string, features:list<string>, sort:int}> */
    private static function records(): array
    {
        if (self::$records !== null) {
            return self::$records;
        }

        try {
            self::ensureWorkspaceLimitColumn();
            $rows = Database::query(
                'SELECT code, name, features, workspace_limit, sort_order FROM subscription_plans ORDER BY sort_order ASC, code ASC'
            )->fetchAll();
        } catch (\Throwable $exception) {
            self::$records = self::builtin();

            return self::$records;
        }

        if ($rows === []) {
            self::seedDefaults();
            $rows = Database::query(
                'SELECT code, name, features, workspace_limit, sort_order FROM subscription_plans ORDER BY sort_order ASC, code ASC'
            )->fetchAll();
        }

        $records = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['features'], true);
            $records[(string) $row['code']] = [
                'name' => (string) $row['name'],
                'features' => self::sanitize(is_array($decoded) ? $decoded : []),
                'sort' => (int) $row['sort_order'],
                'limit' => $row['workspace_limit'] === null ? null : (int) $row['workspace_limit'],
            ];
        }

        self::$records = $records === [] ? self::builtin() : $records;

        return self::$records;
    }

    private static function seedDefaults(): void
    {
        $count = (int) Database::query('SELECT COUNT(*) FROM subscription_plans')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::builtin() as $code => $record) {
            Database::query(
                'INSERT INTO subscription_plans (code, name, features, workspace_limit, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$code, $record['name'], self::encode($record['features']), $record['limit'], $record['sort'], $now, $now]
            );
        }
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;
        $user = Auth::user();
        if ($user === null || $user->organization_id === null) {
            self::$current = null;
            return;
        }

        $organization = $user->organization();
        self::$current = $organization instanceof Organization ? self::saved($organization) : null;
    }
}
