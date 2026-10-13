<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;

final class WorkspaceService
{
    public function create(User $actor, string $name, string $industry = ''): User
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 180) {
            throw new \RuntimeException('Enter a workspace name of 180 characters or fewer.');
        }

        $allowance = FeatureService::workspaceAllowance($actor->email);
        if (!$allowance['can_create']) {
            $limit = (int) $allowance['limit'];
            throw new \RuntimeException('This account already has ' . $limit . ' ' . ($limit === 1 ? 'workspace' : 'workspaces') . ', which is the ' . (FeatureService::planNames()[$allowance['plan']] ?? $allowance['plan']) . ' plan limit.');
        }

        $current = $actor->organization_id !== null ? Organization::find((int) $actor->organization_id) : null;
        $plan = $allowance['plan'];
        $now = date('Y-m-d H:i:s');
        $organizationId = Organization::create([
            'name' => $name,
            'slug' => Organization::uniqueSlug($name),
            'industry' => $industry !== '' ? $industry : null,
            'email' => $actor->email,
            'currency' => $current?->currency ?: 'ETB',
            'timezone' => $current?->timezone ?: 'Africa/Addis_Ababa',
            'country' => $current?->country ?: 'Ethiopia',
            'status' => 'active',
            'subscription_plan' => $plan,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Organization::updateById($organizationId, [
            'enabled_features' => FeatureService::encode(FeatureService::forPlan($plan)),
            'show_guide' => 1,
            'show_time' => 0,
        ]);

        $role = Role::findBySlug('owner');
        if (!$role instanceof Role) {
            throw new \RuntimeException('The owner role is missing.');
        }

        $userId = User::create([
            'organization_id' => $organizationId,
            'role_id' => (int) $role->id,
            'name' => $actor->name,
            'email' => $actor->email,
            'phone' => $actor->phone,
            'job_title' => 'Owner',
            'status' => 'active',
            'joining_date' => date('Y-m-d'),
            'password_hash' => $actor->password_hash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        \App\Models\ProjectStatus::ensureDefaults($organizationId);
        \App\Models\TaskStatus::ensureDefaults($organizationId);
        \App\Models\DeliverableStatus::ensureDefaults($organizationId);
        \App\Models\WorkflowTransition::ensureDefaults($organizationId);
        \App\Models\ActivityLog::record($organizationId, $userId, 'workspace.created', $actor->name . ' created workspace "' . $name . '"', 'organization', $organizationId);

        $created = User::find($userId);
        if (!$created instanceof User) {
            throw new \RuntimeException('The workspace could not be opened.');
        }

        return $created;
    }

    public function open(User $created): void
    {
        Auth::login($created);
    }
}
