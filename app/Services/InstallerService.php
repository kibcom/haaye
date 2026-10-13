<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

final class InstallerService
{
    public static function isInstalled(): bool
    {
        return is_file(BASE_PATH . '/storage/.installed');
    }

    public function install(array $input, bool $withDemo): array
    {
        $this->writeEnv($input);
        \App\Core\Env::load(BASE_PATH . '/.env');
        Config::load(BASE_PATH . '/config');
        Database::reset();

        $this->createDatabase($input);
        Database::reset();
        $this->runSchema();
        Phase2Setup::applySchema();
        Phase3Setup::applySchema();
        Phase4Setup::applySchema();
        Phase5Setup::applySchema();
        Phase6Setup::applySchema();
        Phase7Setup::applySchema();
        Phase8Setup::applySchema();
        Phase9Setup::applySchema();
        Phase10Setup::applySchema();
        Phase11Setup::applySchema();
        Phase12Setup::applySchema();
        Phase13Setup::applySchema();
        Phase14Setup::applySchema();
        FeatureService::applySchema();
        $this->seedRolesAndPermissions();
        Phase2Setup::seedPermissions();
        Phase3Setup::seedPermissions();
        Phase4Setup::seedPermissions();
        Phase5Setup::seedPermissions();
        Phase6Setup::seedPermissions();
        Phase7Setup::seedPermissions();
        Phase8Setup::seedPermissions();
        Phase9Setup::seedPermissions();
        Phase10Setup::seedPermissions();
        Phase11Setup::seedPermissions();
        Phase12Setup::seedPermissions();
        Phase13Setup::seedPermissions();
        Phase14Setup::seedPermissions();
        FeatureService::seedPermissions();

        $superAdmin = $this->createSuperAdmin($input);
        $demo = $withDemo ? $this->seedDemo() : null;

        file_put_contents(BASE_PATH . '/storage/.installed', date('c') . PHP_EOL);

        return [
            'super_admin' => $superAdmin,
            'demo' => $demo,
        ];
    }

    private function writeEnv(array $input): void
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $appUrl = rtrim((string) $input['app_url'], '/');
        $basePath = (string) ($input['app_base_path'] ?? '');
        if ($basePath === '') {
            $urlPath = parse_url($appUrl, PHP_URL_PATH);
            $basePath = is_string($urlPath) ? rtrim($urlPath, '/') : '';
        }

        $lines = [
            'APP_NAME="Haaye Manager"',
            'APP_ENV=' . ($input['app_env'] ?? 'local'),
            'APP_DEBUG=' . (($input['app_debug'] ?? 'true') === 'true' ? 'true' : 'false'),
            'APP_URL=' . $appUrl,
            'APP_BASE_PATH=' . $basePath,
            'APP_KEY=' . $key,
            'APP_TIMEZONE=' . ($input['app_timezone'] ?? 'UTC'),
            '',
            'DB_HOST=' . $input['db_host'],
            'DB_PORT=' . $input['db_port'],
            'DB_DATABASE=' . $input['db_database'],
            'DB_USERNAME=' . $input['db_username'],
            'DB_PASSWORD=' . $input['db_password'],
            '',
            'MAIL_DRIVER=log',
            '',
            'SESSION_SECURE=false',
            '',
        ];

        file_put_contents(BASE_PATH . '/.env', implode(PHP_EOL, $lines));
    }

    private function createDatabase(array $input): void
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $input['db_database']);
        if ($name === '' || $name === null) {
            throw new \RuntimeException('Invalid database name.');
        }

        $pdo = Database::connectWithoutDatabase();
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function runSchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/001_phase1_foundation.sql');
        if ($sql === false) {
            throw new \RuntimeException('Migration file is missing.');
        }

        $pdo = Database::connection();
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    private function seedRolesAndPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = Database::connection();

        $roles = [
            ['Super Admin', 'super_admin', 'Platform administrator', 'platform'],
            ['Owner', 'owner', 'Organization owner', 'organization'],
            ['Admin', 'admin', 'Organization administrator', 'organization'],
            ['Manager', 'manager', 'Department or team manager', 'organization'],
            ['Project Manager', 'project_manager', 'Assigned project manager', 'organization'],
            ['Team Leader', 'team_leader', 'Team task coordinator', 'organization'],
            ['Employee', 'employee', 'Organization employee', 'organization'],
            ['Finance', 'finance', 'Finance and billing role', 'organization'],
            ['Sales', 'sales', 'Wins clients and starts the production workflow', 'organization'],
            ['General Manager', 'general_manager', 'Sees all projects and can approve work', 'organization'],
            ['Operations Manager', 'operations_manager', 'Sees all projects and can approve work', 'organization'],
            ['Social Media Manager', 'social_media_manager', 'Plans and publishes social content', 'organization'],
            ['Editor', 'editor', 'Edits footage and written deliverables', 'organization'],
            ['Videographer', 'videographer', 'Captures and delivers video', 'organization'],
            ['Site Locator', 'site_locator', 'Finds and prepares shoot locations', 'organization'],
            ['Client', 'client', 'Restricted client portal role', 'organization'],
        ];

        $roleIds = [];
        $roleStmt = $pdo->prepare(
            'INSERT INTO roles (name, slug, description, scope, is_system, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        foreach ($roles as $role) {
            $roleStmt->execute([$role[0], $role[1], $role[2], $role[3], $now, $now]);
            $roleIds[$role[1]] = (int) $pdo->lastInsertId();
        }

        $permissions = [
            ['Platform', 'Access platform console', 'platform.access'],
            ['Platform', 'View organizations', 'platform.organizations.view'],
            ['Platform', 'Create organizations', 'platform.organizations.create'],
            ['Platform', 'Update organizations', 'platform.organizations.update'],
            ['Platform', 'View platform users', 'platform.users.view'],
            ['Platform', 'Create platform users', 'platform.users.create'],
            ['Platform', 'Update platform users', 'platform.users.update'],
            ['Platform', 'View plans', 'platform.plans.view'],
            ['Platform', 'Update plans', 'platform.plans.update'],
            ['Dashboard', 'View dashboard', 'dashboard.view'],
            ['Organization', 'View organization', 'organization.view'],
            ['Organization', 'Update organization', 'organization.update'],
            ['Users', 'View users', 'users.view'],
            ['Users', 'Create users', 'users.create'],
            ['Users', 'Update users', 'users.update'],
            ['Users', 'Activate or deactivate users', 'users.activate'],
            ['Users', 'Reset user passwords', 'users.reset_password'],
            ['Roles', 'View roles', 'roles.view'],
            ['Roles', 'Assign roles', 'roles.assign'],
            ['Profile', 'View own profile', 'profile.view'],
            ['Profile', 'Update own profile', 'profile.update'],
            ['Clients', 'View clients', 'clients.view'],
            ['Clients', 'Create clients', 'clients.create'],
            ['Clients', 'Update clients', 'clients.update'],
            ['Clients', 'Change client status', 'clients.activate'],
            ['Clients', 'Manage client contacts', 'clients.contacts.manage'],
        ];

        $permissionIds = [];
        $permStmt = $pdo->prepare('INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)');
        foreach ($permissions as $permission) {
            $permStmt->execute([$permission[1], $permission[2], $permission[0], $now]);
            $permissionIds[$permission[2]] = (int) $pdo->lastInsertId();
        }

        $map = [
            'super_admin' => [
                'platform.access', 'platform.organizations.view', 'platform.organizations.create',
                'platform.organizations.update', 'platform.users.view', 'platform.users.create',
                'platform.users.update', 'platform.plans.view', 'platform.plans.update',
                'profile.view', 'profile.update',
            ],
            'owner' => [
                'dashboard.view', 'organization.view', 'organization.update',
                'users.view', 'users.create', 'users.update', 'users.activate', 'users.reset_password',
                'roles.view', 'roles.assign', 'profile.view', 'profile.update',
                'clients.view', 'clients.create', 'clients.update', 'clients.activate', 'clients.contacts.manage',
            ],
            'admin' => [
                'dashboard.view', 'organization.view', 'organization.update',
                'users.view', 'users.create', 'users.update', 'users.activate', 'users.reset_password',
                'roles.view', 'roles.assign', 'profile.view', 'profile.update',
                'clients.view', 'clients.create', 'clients.update', 'clients.activate', 'clients.contacts.manage',
            ],
            'manager' => [
                'dashboard.view', 'organization.view', 'users.view', 'roles.view', 'profile.view', 'profile.update',
                'clients.view', 'clients.create', 'clients.update', 'clients.contacts.manage',
            ],
            'project_manager' => ['dashboard.view', 'organization.view', 'profile.view', 'profile.update', 'clients.view'],
            'team_leader' => ['dashboard.view', 'profile.view', 'profile.update'],
            'employee' => ['dashboard.view', 'profile.view', 'profile.update'],
            'finance' => ['dashboard.view', 'organization.view', 'profile.view', 'profile.update', 'clients.view'],
            'sales' => [
                'dashboard.view', 'organization.view', 'profile.view', 'profile.update',
                'clients.view', 'clients.create', 'clients.update', 'clients.activate', 'clients.contacts.manage',
            ],
            'general_manager' => ['dashboard.view', 'organization.view', 'profile.view', 'profile.update'],
            'operations_manager' => ['dashboard.view', 'organization.view', 'profile.view', 'profile.update'],
            'social_media_manager' => ['dashboard.view', 'profile.view', 'profile.update'],
            'editor' => ['dashboard.view', 'profile.view', 'profile.update'],
            'videographer' => ['dashboard.view', 'profile.view', 'profile.update'],
            'site_locator' => ['dashboard.view', 'profile.view', 'profile.update'],
            'client' => ['dashboard.view', 'profile.view', 'profile.update'],
        ];

        $link = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($map as $slug => $slugs) {
            foreach ($slugs as $permissionSlug) {
                $link->execute([$roleIds[$slug], $permissionIds[$permissionSlug]]);
            }
        }
    }

    private function createSuperAdmin(array $input): array
    {
        $role = Role::findBySlug('super_admin');
        if (!$role instanceof Role) {
            throw new \RuntimeException('Super Admin role is missing.');
        }

        $now = date('Y-m-d H:i:s');
        User::create([
            'organization_id' => null,
            'role_id' => $role->id,
            'name' => $input['admin_name'],
            'email' => $input['admin_email'],
            'phone' => null,
            'job_title' => 'Platform Administrator',
            'status' => 'active',
            'joining_date' => date('Y-m-d'),
            'password_hash' => password_hash($input['admin_password'], PASSWORD_DEFAULT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'email' => $input['admin_email'],
        ];
    }

    private function seedDemo(): array
    {
        $now = date('Y-m-d H:i:s');
        $orgId = Organization::create([
            'name' => 'Abay Consulting',
            'slug' => 'abay-consulting',
            'industry' => 'Consulting',
            'email' => 'hello@northline.test',
            'phone' => '+251 11 554 2100',
            'address' => 'Bole Road, Africa Avenue',
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'tax_id' => 'TIN-00884210',
            'website' => 'https://abayconsulting.test',
            'status' => 'active',
            'subscription_plan' => 'PROFESSIONAL',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $users = [
            ['owner', 'Hanna Tadesse', 'owner@haaye.com', 'Managing Partner'],
            ['admin', 'Dawit Alemu', 'dawit@haaye.com', 'Operations Admin'],
            ['manager', 'Yared Mekonnen', 'manager@haaye.com', 'Delivery Manager'],
            ['employee', 'Liya Tesfaye', 'employee@haaye.com', 'Consultant'],
            ['finance', 'Bethelhem Girma', 'finance@haaye.com', 'Finance Lead'],
            ['client', 'Abel Worku', 'client@haaye.com', 'Client Contact'],
        ];

        $password = 'DemoPass123!';
        foreach ($users as $item) {
            $role = Role::findBySlug($item[0]);
            if (!$role instanceof Role) {
                continue;
            }

            User::create([
                'organization_id' => $orgId,
                'role_id' => $role->id,
                'name' => $item[1],
                'email' => $item[2],
                'phone' => null,
                'job_title' => $item[3],
                'employee_code' => strtoupper(substr($item[0], 0, 3)) . '-1001',
                'status' => 'active',
                'joining_date' => '2024-01-15',
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Phase2Setup::seedDemo($orgId);
        Phase3Setup::seedForOrganizations();
        Phase4Setup::seedForOrganizations();
        Phase5Setup::seedForOrganizations();
        Phase6Setup::seedForOrganizations();
        Phase7Setup::seedForOrganizations();
        Phase8Setup::seedForOrganizations();
        Phase9Setup::enableFeatures();
        Phase9Setup::seedSamples();
        Phase9Setup::seedWorkspaces();
        Phase10Setup::enableFeatures();
        Phase10Setup::seedPipelineRoles();
        Phase10Setup::seedSampleUsers();
        Phase10Setup::seedSampleTasks();
        Phase14Setup::applySchema();
        Phase14Setup::seedPermissions();
        Phase14DemoSetup::seed();
        LuminaDemoSetup::seed();
        BillingService::seedAll();

        return [
            'organization' => 'Abay Consulting',
            'password' => $password,
            'users' => array_column($users, 2),
        ];
    }
}
