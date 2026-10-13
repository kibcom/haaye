<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\FileUploader;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\ProjectStatus;
use App\Models\TaskStatus;
use App\Models\DeliverableStatus;
use App\Models\WorkflowTransition;
use App\Models\Role;
use App\Models\User;

final class OrganizationService
{
    public function create(array $data, ?array $logoFile = null, ?array $owner = null, ?int $actorId = null): Organization
    {
        $now = date('Y-m-d H:i:s');
        $logo = $logoFile ? FileUploader::image($logoFile, 'logos') : null;

        $id = Organization::create([
            'name' => $data['name'],
            'slug' => Organization::uniqueSlug($data['name']),
            'logo' => $logo,
            'industry' => $data['industry'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null,
            'country' => $data['country'] ?: null,
            'currency' => $data['currency'] ?: 'USD',
            'timezone' => $data['timezone'] ?: 'UTC',
            'tax_id' => $data['tax_id'] ?: null,
            'tax_information' => $data['tax_information'] ?: null,
            'website' => $data['website'] ?: null,
            'status' => $data['status'] ?? 'active',
            'subscription_plan' => $data['subscription_plan'] ?? 'FREE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($owner !== null) {
            $role = Role::findBySlug('owner');
            if ($role instanceof Role) {
                User::create([
                    'organization_id' => $id,
                    'role_id' => $role->id,
                    'name' => $owner['name'],
                    'email' => $owner['email'],
                    'phone' => null,
                    'job_title' => 'Owner',
                    'status' => 'active',
                    'joining_date' => date('Y-m-d'),
                    'password_hash' => password_hash($owner['password'], PASSWORD_DEFAULT),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $extra = [];
        if (array_key_exists('enabled_features', $data)) {
            $extra['enabled_features'] = $data['enabled_features'];
        }
        if (array_key_exists('show_guide', $data)) {
            $extra['show_guide'] = (int) $data['show_guide'] === 1 ? 1 : 0;
        }
        if (array_key_exists('show_time', $data)) {
            $extra['show_time'] = (int) $data['show_time'] === 1 ? 1 : 0;
        }
        if ($extra !== []) {
            Organization::updateById($id, $extra);
        }

        ProjectStatus::ensureDefaults($id);
        TaskStatus::ensureDefaults($id);
        DeliverableStatus::ensureDefaults($id);
        WorkflowTransition::ensureDefaults($id);
        ActivityLog::record($id, $actorId, 'organization.created', 'Organization "' . $data['name'] . '" was created', 'organization', $id);

        $organization = Organization::find($id);
        if (!$organization instanceof Organization) {
            throw new \RuntimeException('Organization could not be created.');
        }

        return $organization;
    }

    public function update(Organization $organization, array $data, ?array $logoFile = null, ?int $actorId = null, bool $removeLogo = false): void
    {
        $payload = [
            'name' => $data['name'],
            'industry' => $data['industry'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null,
            'country' => $data['country'] ?: null,
            'currency' => $data['currency'] ?: 'USD',
            'timezone' => $data['timezone'] ?: 'UTC',
            'tax_id' => $data['tax_id'] ?: null,
            'tax_information' => $data['tax_information'] ?: null,
            'website' => $data['website'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['invoice_prefix'])) {
            $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9-]/', '', (string) $data['invoice_prefix']));
            if (!preg_match('/^[A-Z0-9-]{1,12}$/', $prefix)) {
                throw new \RuntimeException('Invoice prefix may use letters, numbers, and hyphens.');
            }
            $payload['invoice_prefix'] = $prefix;
        }

        if (isset($data['invoice_next_number'])) {
            $next = (int) $data['invoice_next_number'];
            if ($next < 1 || $next > 999999) {
                throw new \RuntimeException('The next invoice number must be between 1 and 999999.');
            }
            $payload['invoice_next_number'] = $next;
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (isset($data['subscription_plan'])) {
            $payload['subscription_plan'] = $data['subscription_plan'];
        }

        if (array_key_exists('enabled_features', $data)) {
            $payload['enabled_features'] = $data['enabled_features'];
        }

        if (array_key_exists('show_guide', $data)) {
            $payload['show_guide'] = (int) $data['show_guide'] === 1 ? 1 : 0;
        }

        if (array_key_exists('show_time', $data)) {
            $payload['show_time'] = (int) $data['show_time'] === 1 ? 1 : 0;
        }

        $previousLogo = (string) ($organization->logo ?? '');
        $newLogo = FileUploader::image($logoFile, 'logos');
        if ($newLogo !== null) {
            $payload['logo'] = $newLogo;
            if ($previousLogo !== '' && $previousLogo !== $newLogo) {
                FileUploader::deletePublicImage($previousLogo);
            }
        } elseif ($removeLogo && $previousLogo !== '') {
            $payload['logo'] = null;
            FileUploader::deletePublicImage($previousLogo);
        }

        Organization::updateById((int) $organization->id, $payload);
        ActivityLog::record(
            (int) $organization->id,
            $actorId,
            'organization.updated',
            'Organization "' . $data['name'] . '" was updated',
            'organization',
            (int) $organization->id
        );
    }
}
