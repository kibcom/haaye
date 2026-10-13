<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;

final class Phase2Setup
{
    public static function applySchema(): void
    {
        $sql = file_get_contents(BASE_PATH . '/database/migrations/002_phase2_clients.sql');
        if ($sql === false) {
            throw new \RuntimeException('Phase 2 migration file is missing.');
        }

        $pdo = Database::connection();
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function seedPermissions(): void
    {
        $now = date('Y-m-d H:i:s');
        $permissions = [
            ['Clients', 'View clients', 'clients.view'],
            ['Clients', 'View all clients (not only assigned to them)', 'clients.view_all'],
            ['Clients', 'Create clients', 'clients.create'],
            ['Clients', 'Update clients', 'clients.update'],
            ['Clients', 'Change client status', 'clients.activate'],
            ['Clients', 'Manage client contacts', 'clients.contacts.manage'],
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

        $map = [
            // view_all = company-wide clients; leave off for sales so they only see assigned accounts.
            'owner' => ['clients.view', 'clients.view_all', 'clients.create', 'clients.update', 'clients.activate', 'clients.contacts.manage'],
            'admin' => ['clients.view', 'clients.view_all', 'clients.create', 'clients.update', 'clients.activate', 'clients.contacts.manage'],
            'sales' => ['clients.view', 'clients.create', 'clients.update', 'clients.activate', 'clients.contacts.manage'],
            'manager' => ['clients.view', 'clients.view_all', 'clients.create', 'clients.update', 'clients.contacts.manage'],
            'general_manager' => ['clients.view', 'clients.view_all'],
            'operations_manager' => ['clients.view', 'clients.view_all'],
            'project_manager' => ['clients.view', 'clients.view_all'],
            'finance' => ['clients.view', 'clients.view_all'],
        ];

        foreach ($map as $roleSlug => $slugs) {
            $role = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', [$roleSlug])->fetch();
            if (!$role) {
                continue;
            }

            foreach ($slugs as $permissionSlug) {
                AuthorizationService::grantPermissionToDefaultRole(
                    (int) $role['id'],
                    (int) ($permissionIds[$permissionSlug] ?? 0)
                );
            }
        }
    }

    public static function seedDemo(int $organizationId): void
    {
        $now = date('Y-m-d H:i:s');
        $manager = User::findByEmail('manager@haaye.com');
        $owner = User::findByEmail('owner@haaye.com');

        $clients = [
            [
                'name' => 'Zemen Media',
                'contact_name' => 'Marta Hailu',
                'email' => 'maya@heliosmedia.test',
                'phone' => '+251 11 667 1900',
                'address' => 'Mexico Square, Churchill Avenue',
                'city' => 'Addis Ababa',
                'country' => 'Ethiopia',
                'tax_id' => 'TIN-00441902',
                'website' => 'https://zemenmedia.test',
                'industry' => 'Digital Marketing',
                'status' => 'active',
                'notes' => 'Retainer client for brand and campaign work.',
                'assigned_user_id' => $manager?->id,
                'contacts' => [
                    ['Marta Hailu', 'maya@heliosmedia.test', '+251 11 667 1900', 'Marketing Director', 1],
                    ['Nahom Desta', 'owen@heliosmedia.test', '+251 11 667 1901', 'Producer', 0],
                ],
                'projects' => [
                    ['Zemen brand refresh', 'ZMN-BR', 'planning', 'high', '2026-10-01', '2026-12-15'],
                ],
            ],
            [
                'name' => 'Awash Tax Advisors',
                'contact_name' => 'Tigist Abebe',
                'email' => 'ruth@harbortax.test',
                'phone' => '+251 11 551 0172',
                'address' => 'Kazanchis, Debre Zeit Road',
                'city' => 'Addis Ababa',
                'country' => 'Ethiopia',
                'tax_id' => 'TIN-00902118',
                'website' => 'https://awashtax.test',
                'industry' => 'Accounting',
                'status' => 'lead',
                'notes' => 'Evaluating a document-collection and review workflow.',
                'assigned_user_id' => $owner?->id,
                'contacts' => [
                    ['Tigist Abebe', 'ruth@harbortax.test', '+251 11 551 0172', 'Managing Partner', 1],
                ],
                'projects' => [],
            ],
            [
                'name' => 'Bishoftu Construction',
                'contact_name' => 'Kebede Lemma',
                'email' => 'carlos@brightline.test',
                'phone' => '+251 11 433 0133',
                'address' => 'Industrial Zone',
                'city' => 'Bishoftu',
                'country' => 'Ethiopia',
                'tax_id' => 'TIN-00773001',
                'website' => 'https://bishoftuconstruction.test',
                'industry' => 'Construction',
                'status' => 'inactive',
                'notes' => 'Paused until next fiscal year.',
                'assigned_user_id' => $manager?->id,
                'contacts' => [
                    ['Kebede Lemma', 'carlos@brightline.test', '+251 11 433 0133', 'Operations Lead', 1],
                ],
                'projects' => [
                    ['Bishoftu site pack', 'BSH-ONB', 'on_hold', 'medium', '2026-06-01', '2026-07-30'],
                ],
            ],
        ];

        foreach ($clients as $item) {
            $clientId = Client::create([
                'organization_id' => $organizationId,
                'name' => $item['name'],
                'contact_name' => $item['contact_name'],
                'email' => $item['email'],
                'phone' => $item['phone'],
                'address' => $item['address'],
                'city' => $item['city'],
                'country' => $item['country'],
                'tax_id' => $item['tax_id'],
                'website' => $item['website'],
                'industry' => $item['industry'],
                'status' => $item['status'],
                'notes' => $item['notes'],
                'assigned_user_id' => $item['assigned_user_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($item['contacts'] as $contact) {
                ClientContact::create([
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'name' => $contact[0],
                    'email' => $contact[1],
                    'phone' => $contact[2],
                    'job_title' => $contact[3],
                    'is_primary' => $contact[4],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($item['projects'] as $project) {
                Project::create([
                    'organization_id' => $organizationId,
                    'client_id' => $clientId,
                    'name' => $project[0],
                    'code' => $project[1],
                    'status' => $project[2],
                    'priority' => $project[3],
                    'start_date' => $project[4],
                    'due_date' => $project[5],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
