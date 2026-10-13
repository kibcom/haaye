<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Booking;
use App\Models\Lead;

final class Phase9Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/009_crm_bookings.sql');
        if ($sql === false) {
            throw new \RuntimeException('CRM and bookings migration file is missing.');
        }

        $pdo = Database::connection();
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Leads', 'View leads', 'leads.view'],
            ['Leads', 'View all leads (not only owned by them)', 'leads.view_all'],
            ['Leads', 'Create leads', 'leads.create'],
            ['Leads', 'Update leads', 'leads.update'],
            ['Leads', 'Convert leads', 'leads.convert'],
            ['Bookings', 'View bookings', 'bookings.view'],
            ['Bookings', 'Create bookings', 'bookings.create'],
            ['Bookings', 'Update bookings', 'bookings.update'],
            ['Activity', 'View activity', 'activity.view'],
            ['Calendar', 'View calendar', 'calendar.view'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $row = Database::query('SELECT id FROM permissions WHERE slug = ? LIMIT 1', [$permission[2]])->fetch();
            if ($row) {
                Database::query(
                    'UPDATE permissions SET name = ?, group_name = ? WHERE id = ?',
                    [$permission[1], $permission[0], (int) $row['id']]
                );
                $permissionIds[$permission[2]] = (int) $row['id'];
                continue;
            }

            Database::query(
                'INSERT INTO permissions (name, slug, group_name, created_at) VALUES (?, ?, ?, ?)',
                [$permission[1], $permission[2], $permission[0], $now]
            );
            $permissionIds[$permission[2]] = (int) Database::lastInsertId();
        }

        self::assignFromRoleAccess($permissionIds);
        self::grantViewAllDefaults($permissionIds);
        self::grantCustomUsers($permissionIds);
    }

    /**
     * Grant new feature access from what each role already has.
     *
     * @param array<string, int> $permissionIds
     */
    private static function assignFromRoleAccess(array $permissionIds): void
    {
        $roles = Database::query(
            "SELECT id, slug FROM roles WHERE scope = 'organization'"
        )->fetchAll();

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $held = Database::query(
                'SELECT p.slug
                 FROM permissions p
                 INNER JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = ?',
                [$roleId]
            )->fetchAll();
            $slugs = array_map(static fn (array $row): string => (string) $row['slug'], $held);
            $grant = [];

            if (in_array('clients.view', $slugs, true)) {
                $grant[] = 'leads.view';
            }
            if (in_array('clients.view_all', $slugs, true)) {
                $grant[] = 'leads.view_all';
            }
            if (in_array('clients.create', $slugs, true)) {
                $grant[] = 'leads.create';
            }
            if (in_array('clients.update', $slugs, true)) {
                $grant[] = 'leads.update';
            }
            if (in_array('clients.create', $slugs, true) && in_array('clients.update', $slugs, true)) {
                $grant[] = 'leads.convert';
            }

            if (in_array('projects.view', $slugs, true) || in_array('projects.view_all', $slugs, true)) {
                $grant[] = 'bookings.view';
            }
            if (in_array('projects.create', $slugs, true) || in_array('projects.update', $slugs, true)) {
                $grant[] = 'bookings.create';
                $grant[] = 'bookings.update';
            }

            if (in_array('dashboard.view', $slugs, true) && (string) $role['slug'] !== 'client') {
                $grant[] = 'activity.view';
            }

            if (in_array('tasks.view', $slugs, true)) {
                $grant[] = 'calendar.view';
            }

            foreach (array_unique($grant) as $permissionSlug) {
                if (!isset($permissionIds[$permissionSlug])) {
                    continue;
                }
                AuthorizationService::grantPermissionToDefaultRole($roleId, (int) $permissionIds[$permissionSlug]);
            }
        }
    }

    /**
     * Company-wide leads: managers/admins. Sales stays on own leads unless admin ticks view_all.
     *
     * @param array<string, int> $permissionIds
     */
    private static function grantViewAllDefaults(array $permissionIds): void
    {
        $viewAllId = (int) ($permissionIds['leads.view_all'] ?? 0);
        if ($viewAllId < 1) {
            return;
        }

        foreach (['owner', 'admin', 'manager', 'general_manager', 'operations_manager', 'project_manager', 'finance'] as $slug) {
            $role = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug])->fetch();
            if (!$role) {
                continue;
            }
            AuthorizationService::grantPermissionToDefaultRole((int) $role['id'], $viewAllId);
        }
    }

    /** @param array<string, int> $permissionIds */
    private static function grantCustomUsers(array $permissionIds): void
    {
        $bundles = [
            'clients.view' => ['leads.view'],
            'clients.view_all' => ['leads.view_all'],
            'clients.create' => ['leads.create'],
            'clients.update' => ['leads.update'],
            'projects.view' => ['bookings.view'],
            'projects.view_all' => ['bookings.view'],
            'projects.create' => ['bookings.create', 'bookings.update'],
            'projects.update' => ['bookings.create', 'bookings.update'],
            'dashboard.view' => ['activity.view'],
            'tasks.view' => ['calendar.view'],
        ];
        $users = Database::query('SELECT id FROM users WHERE custom_permissions = 1')->fetchAll();
        foreach ($users as $user) {
            $held = Database::query(
                'SELECT p.slug FROM permissions p INNER JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?',
                [(int) $user['id']]
            )->fetchAll();
            $slugs = array_map(static fn (array $row): string => (string) $row['slug'], $held);
            $grant = [];
            foreach ($bundles as $has => $add) {
                if (in_array($has, $slugs, true)) {
                    foreach ($add as $slug) {
                        $grant[$slug] = true;
                    }
                }
            }
            if (in_array('clients.create', $slugs, true) && in_array('clients.update', $slugs, true)) {
                $grant['leads.convert'] = true;
            }
            foreach (array_keys($grant) as $slug) {
                if (!isset($permissionIds[$slug]) || in_array($slug, $slugs, true)) {
                    continue;
                }
                Database::query(
                    'INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)',
                    [(int) $user['id'], $permissionIds[$slug]]
                );
            }
        }
    }

    public static function seedWorkspaces(): void
    {
        $owner = Database::query(
            "SELECT u.id FROM users u
             INNER JOIN organizations o ON o.id = u.organization_id
             WHERE u.email = ? AND o.slug = ? LIMIT 1",
            ['owner@haaye.com', 'abay-consulting']
        )->fetch();
        if (!$owner) {
            return;
        }
        $user = \App\Models\User::find((int) $owner['id']);
        if (!$user instanceof \App\Models\User) {
            return;
        }

        $service = new WorkspaceService();
        foreach ([['Nile Studio', 'Design'], ['Skyline Desk', 'Hospitality']] as $sample) {
            $exists = Database::query('SELECT id FROM organizations WHERE name = ? LIMIT 1', [$sample[0]])->fetch();
            if ($exists) {
                continue;
            }
            $service->create($user, $sample[0], $sample[1]);
        }
    }

    public static function seedSamples(): void
    {
        $org = Database::query("SELECT id FROM organizations WHERE slug = 'abay-consulting' LIMIT 1")->fetch();
        if (!$org) {
            return;
        }

        $orgId = (int) $org['id'];
        $now = date('Y-m-d H:i:s');
        $ownerId = self::userInOrg($orgId, 'owner');
        $managerId = self::userInOrg($orgId, 'manager') ?? $ownerId;

        $leads = [
            ['Selam Bekele', 'Nile Coffee Roasters', 'selam@nilecoffee.test', '+251 11 618 2201', 'website', 'new', '28000.00', 'Asked for a brand and packaging review after the harvest season.', $ownerId],
            ['Yonas Alemu', 'Skyline Hotels', 'yonas@skylinehotels.test', '+251 11 554 0190', 'call', 'contacted', '46000.00', 'Wants a booking desk for group stays and event rooms.', $managerId],
            ['Helen Desta', 'Addis Legal Partners', 'helen@addislegal.test', '+251 11 442 7781', 'referral', 'qualified', '62000.00', 'Ready for a proposal on a client portal and matter tracking.', $ownerId],
            ['Sara Mekonnen', 'Entoto Tours', 'sara@entototours.test', '+251 11 320 1144', 'event', 'new', '15000.00', 'Met at a trade fair. Needs a simple itinerary site.', $managerId],
            ['Abel Girma', 'Merkato Traders', 'abel@merkatotraders.test', '+251 11 275 6602', 'email', 'lost', '9000.00', 'Chose another vendor for this season.', $ownerId],
        ];

        foreach ($leads as $lead) {
            $exists = Database::query(
                'SELECT id FROM leads WHERE organization_id = ? AND email = ? LIMIT 1',
                [$orgId, $lead[2]]
            )->fetch();
            if ($exists) {
                continue;
            }
            Lead::create([
                'organization_id' => $orgId,
                'name' => $lead[0],
                'company' => $lead[1],
                'email' => $lead[2],
                'phone' => $lead[3],
                'source' => $lead[4],
                'status' => $lead[5],
                'estimated_value' => $lead[6],
                'notes' => $lead[7],
                'owner_user_id' => $lead[8],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $projects = [];
        $rows = Database::query(
            'SELECT id, name FROM projects WHERE organization_id = ?',
            [$orgId]
        )->fetchAll();
        foreach ($rows as $row) {
            $projects[(string) $row['name']] = (int) $row['id'];
        }

        $bookings = [
            ['Zemen brand refresh', 'Creative review', 'Marta Hailu', '2026-10-08 10:00:00', '2026-10-08 11:30:00', 'confirmed', 'Bole Atlas, Addis Ababa', 'Review the two concept directions.'],
            ['Zemen brand refresh', 'Launch planning', 'Nahom Desta', '2026-10-21 14:00:00', '2026-10-21 15:00:00', 'scheduled', 'Video call', 'Lock the launch week and asset list.'],
            ['Zemen brand refresh', 'Discovery workshop', 'Marta Hailu', '2026-09-18 09:30:00', '2026-09-18 11:00:00', 'completed', 'Mexico Square', 'Brand audit and audience notes.'],
            ['Awash onboarding', 'Onboarding workshop', 'Tigist Abebe', '2026-10-09 09:00:00', '2026-10-09 12:00:00', 'scheduled', 'Kazanchis office', 'Walk through document collection and the first review cycle.'],
            ['Bishoftu site pack', 'Site walk', 'Kebede Lemma', '2026-10-04 08:00:00', '2026-10-04 11:00:00', 'confirmed', 'Industrial Zone, Bishoftu', 'Confirm access, photos, and the pack contents.'],
        ];

        foreach ($bookings as $booking) {
            if (!isset($projects[$booking[0]])) {
                continue;
            }
            $exists = Database::query(
                'SELECT id FROM bookings WHERE organization_id = ? AND project_id = ? AND title = ? LIMIT 1',
                [$orgId, $projects[$booking[0]], $booking[1]]
            )->fetch();
            if ($exists) {
                continue;
            }
            Booking::create([
                'organization_id' => $orgId,
                'project_id' => $projects[$booking[0]],
                'title' => $booking[1],
                'contact_name' => $booking[2],
                'starts_at' => $booking[3],
                'ends_at' => $booking[4],
                'status' => $booking[5],
                'location' => $booking[6],
                'notes' => $booking[7],
                'created_by' => $ownerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        self::seedLuminaLeads($now);
    }

    private static function seedLuminaLeads(string $now): void
    {
        $org = Database::query("SELECT id FROM organizations WHERE slug = 'lumina' LIMIT 1")->fetch();
        if (!$org) {
            return;
        }

        $orgId = (int) $org['id'];
        $ownerId = self::userInOrg($orgId, 'owner') ?? self::userInOrg($orgId, 'admin');
        $managerId = self::userInOrg($orgId, 'manager') ?? $ownerId;
        if ($ownerId === null) {
            return;
        }

        $leads = [
            ['Hana Tesfaye', 'Bole Gallery', 'hana@bolegallery.test', '+251 11 661 2044', 'website', 'new', '34000.00', 'Asked for a short brand film for the next exhibition.', $ownerId],
            ['Dawit Kebede', 'Kuriftu Resorts', 'dawit@kuriftu.test', '+251 11 518 3308', 'call', 'contacted', '78000.00', 'Wants a seasonal campaign and a photo set for the lakeside rooms.', $managerId],
            ['Liya Assefa', 'Sheger FM', 'liya@shegerfm.test', '+251 11 467 9012', 'referral', 'qualified', '41000.00', 'Ready for a proposal on podcast artwork and a social kit.', $ownerId],
            ['Mikael Tadesse', 'Addis Fashion Week', 'mikael@addisfashion.test', '+251 11 392 7750', 'event', 'new', '55000.00', 'Met at the runway preview. Needs a lookbook and sponsor pages.', $managerId],
            ['Ruth Alemu', 'Kaldis Coffee', 'ruth@kaldis.test', '+251 11 629 1186', 'email', 'lost', '12000.00', 'Menu redesign went to an in-house team.', $ownerId],
        ];

        foreach ($leads as $lead) {
            $exists = Database::query(
                'SELECT id FROM leads WHERE organization_id = ? AND email = ? LIMIT 1',
                [$orgId, $lead[2]]
            )->fetch();
            if ($exists) {
                continue;
            }
            Lead::create([
                'organization_id' => $orgId,
                'name' => $lead[0],
                'company' => $lead[1],
                'email' => $lead[2],
                'phone' => $lead[3],
                'source' => $lead[4],
                'status' => $lead[5],
                'estimated_value' => $lead[6],
                'notes' => $lead[7],
                'owner_user_id' => $lead[8],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private static function userInOrg(int $organizationId, string $roleSlug): ?int
    {
        $row = Database::query(
            'SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.organization_id = ? AND r.slug = ? AND u.status = ? LIMIT 1',
            [$organizationId, $roleSlug, 'active']
        )->fetch();

        return $row ? (int) $row['id'] : null;
    }

    public static function enableFeatures(): void
    {
        $rows = Database::query('SELECT code, features FROM subscription_plans')->fetchAll();
        foreach ($rows as $row) {
            $features = json_decode((string) $row['features'], true);
            if (!is_array($features)) {
                continue;
            }
            $next = $features;
            foreach (['leads', 'bookings'] as $slug) {
                if (!in_array($slug, $next, true)) {
                    $next[] = $slug;
                }
            }
            if ($next !== $features) {
                Database::query(
                    'UPDATE subscription_plans SET features = ?, updated_at = ? WHERE code = ?',
                    [json_encode(array_values($next)), date('Y-m-d H:i:s'), $row['code']]
                );
            }
        }

        $organizations = Database::query(
            'SELECT id, enabled_features FROM organizations WHERE enabled_features IS NOT NULL AND enabled_features <> ""'
        )->fetchAll();
        foreach ($organizations as $organization) {
            $features = json_decode((string) $organization['enabled_features'], true);
            if (!is_array($features)) {
                continue;
            }
            $next = $features;
            foreach (['leads', 'bookings'] as $slug) {
                if (!in_array($slug, $next, true)) {
                    $next[] = $slug;
                }
            }
            if ($next !== $features) {
                Database::query(
                    'UPDATE organizations SET enabled_features = ? WHERE id = ?',
                    [json_encode(array_values($next)), (int) $organization['id']]
                );
            }
        }
    }
}
