<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkFlowRole;

/**
 * Sample users for the Lumina Creative tenant (organization_id scoped).
 */
final class LuminaDemoSetup
{
    public const DEMO_PASSWORD = 'DemoPass123!';
    public const ORG_SLUG = 'lumina';

    /** @var list<array{0:string,1:string,2:string,3:string,4:string}> */
    private const EMPLOYEES = [
        ['owner', 'Lina Haile', 'owner@lumina.test', 'Owner', 'LUM-OWN-01'],
        ['general_manager', 'Abel Bekele', 'gm@lumina.test', 'General Manager / CEO', 'LUM-GM-01'],
        ['operations_manager', 'Meron Assefa', 'ops@lumina.test', 'Operations Manager', 'LUM-OPS-01'],
        ['sales', 'Sara Sales', 'sales@lumina.test', 'Sales Executive', 'LUM-SAL-01'],
        ['site_locator', 'Ruth Desta', 'locator@lumina.test', 'Site Locator', 'LUM-LOC-01'],
        ['content_creator', 'Kidist Alemu', 'content@lumina.test', 'Content Creator', 'LUM-CNT-01'],
        ['videographer', 'Samuel Haile', 'video@lumina.test', 'Videographer', 'LUM-VID-01'],
        ['editor', 'Yonatan Kebede', 'editor@lumina.test', 'Editor', 'LUM-EDT-01'],
        ['social_media_manager', 'Hana Girma', 'social@lumina.test', 'Social Media Manager', 'LUM-SMM-01'],
        ['employee', 'Liya Tesfaye', 'employee@lumina.test', 'Team Member', 'LUM-EMP-01'],
    ];

    public static function seed(): void
    {
        $org = Database::query(
            'SELECT id, name FROM organizations WHERE slug = ? LIMIT 1',
            [self::ORG_SLUG]
        )->fetch();
        if (!$org) {
            return;
        }

        $orgId = (int) $org['id'];
        Phase10Setup::seedRoles();
        Phase10Setup::seedPermissions();
        Phase14Setup::applySchema();
        Phase14Setup::seedPermissions();
        self::ensureContentCreatorRole();
        Phase10Setup::seedPipelineRoles();

        self::ensureEmployees($orgId);
        self::ensureWorkFlowRoles($orgId);
        self::resetExistingLuminaPasswords($orgId);

        Organization::updateById($orgId, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<string> */
    public static function demoEmails(): array
    {
        $emails = array_column(self::EMPLOYEES, 2);
        // Existing Lumina sample accounts from earlier seeds.
        foreach (['mearg@haaye.com', 'selam@haaye.com', 'natan@haaye.com', 'helen@haaye.com', 'rift.client@haaye.com'] as $email) {
            $emails[] = $email;
        }

        return $emails;
    }

    public static function isDemoUser(?string $email): bool
    {
        if ($email === null || $email === '') {
            return false;
        }

        return in_array(strtolower($email), array_map('strtolower', self::demoEmails()), true);
    }

    private static function ensureEmployees(int $orgId): void
    {
        $now = date('Y-m-d H:i:s');
        $hash = password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT);

        foreach (self::EMPLOYEES as $item) {
            $role = Role::findBySlug($item[0]);
            if (!$role instanceof Role) {
                continue;
            }

            // Prefer org-scoped lookup; email can exist in another company as a different user row.
            $existing = Database::query(
                'SELECT id FROM users WHERE organization_id = ? AND email = ? LIMIT 1',
                [$orgId, $item[2]]
            )->fetch();

            if ($existing) {
                Database::query(
                    'UPDATE users SET role_id = ?, name = ?, job_title = ?, employee_code = ?, status = ?, password_hash = ?, updated_at = ?
                     WHERE id = ? AND organization_id = ?',
                    [$role->id, $item[1], $item[3], $item[4], 'active', $hash, $now, (int) $existing['id'], $orgId]
                );
                continue;
            }

            User::create([
                'organization_id' => $orgId,
                'role_id' => $role->id,
                'name' => $item[1],
                'email' => $item[2],
                'phone' => null,
                'job_title' => $item[3],
                'employee_code' => $item[4],
                'status' => 'active',
                'joining_date' => date('Y-m-d'),
                'password_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private static function resetExistingLuminaPasswords(int $orgId): void
    {
        $now = date('Y-m-d H:i:s');
        $hash = password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT);
        Database::query(
            'UPDATE users SET password_hash = ?, status = ?, updated_at = ? WHERE organization_id = ?',
            [$hash, 'active', $now, $orgId]
        );
    }

    private static function ensureWorkFlowRoles(int $orgId): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (Phase10Setup::DEFAULT_PIPELINE_ROLES as $index => $name) {
            $exists = Database::query(
                'SELECT id FROM work_flow_roles WHERE organization_id = ? AND name = ? LIMIT 1',
                [$orgId, $name]
            )->fetch();
            if ($exists) {
                continue;
            }
            WorkFlowRole::create([
                'organization_id' => $orgId,
                'name' => $name,
                'description' => $name . ' department for Lumina',
                'sort_order' => $index + 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $content = Database::query(
            'SELECT id FROM work_flow_roles WHERE organization_id = ? AND name = ? LIMIT 1',
            [$orgId, 'Content Creator']
        )->fetch();
        if (!$content) {
            WorkFlowRole::create([
                'organization_id' => $orgId,
                'name' => 'Content Creator',
                'description' => 'Captions and creative content',
                'sort_order' => 8,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private static function ensureContentCreatorRole(): void
    {
        // Reuse Phase14 demo helper if role missing.
        $exists = Database::query('SELECT id FROM roles WHERE slug = ? LIMIT 1', ['content_creator'])->fetch();
        if ($exists) {
            return;
        }
        Phase14DemoSetup::seed();
    }
}
