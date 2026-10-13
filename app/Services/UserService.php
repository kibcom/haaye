<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\FileUploader;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;

final class UserService
{
    public function create(int $organizationId, array $data, ?array $photo = null, ?int $actorId = null): User
    {
        $now = date('Y-m-d H:i:s');
        $profilePhoto = $photo ? FileUploader::image($photo, 'profiles') : null;

        $id = User::create([
            'organization_id' => $organizationId,
            'role_id' => (int) $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'profile_photo' => $profilePhoto,
            'job_title' => $data['job_title'] ?: null,
            'employee_code' => $data['employee_code'] ?: null,
            'status' => $data['status'] ?? 'active',
            'joining_date' => $data['joining_date'] ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'user.created', 'User "' . $data['name'] . '" was created', 'user', $id);

        if (array_key_exists('custom_permissions', $data)) {
            $ids = [];
            if (!empty($data['permission_ids']) && is_array($data['permission_ids'])) {
                foreach ($data['permission_ids'] as $permissionId) {
                    if (is_numeric($permissionId)) {
                        $ids[] = (int) $permissionId;
                    }
                }
            }
            AuthorizationService::syncUserPermissions($id, (bool) $data['custom_permissions'], $ids);
        }

        $user = User::findInOrganization($id, $organizationId);
        if (!$user instanceof User) {
            throw new \RuntimeException('User could not be created.');
        }

        return $user;
    }

    public function update(User $user, array $data, ?array $photo = null, ?int $actorId = null): void
    {
        $payload = [
            'name' => $data['name'],
            'email' => strtolower((string) $data['email']),
            'phone' => $data['phone'] ?: null,
            'job_title' => $data['job_title'] ?: null,
            'employee_code' => $data['employee_code'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['role_id'])) {
            $payload['role_id'] = (int) $data['role_id'];
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (isset($data['joining_date'])) {
            $payload['joining_date'] = $data['joining_date'] ?: null;
        }

        if (!empty($data['password'])) {
            $payload['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($photo !== null) {
            $payload['profile_photo'] = FileUploader::image($photo, 'profiles');
        }

        User::updateById((int) $user->id, $payload);
        if (array_key_exists('custom_permissions', $data)) {
            $ids = [];
            if (!empty($data['permission_ids']) && is_array($data['permission_ids'])) {
                foreach ($data['permission_ids'] as $permissionId) {
                    if (is_numeric($permissionId)) {
                        $ids[] = (int) $permissionId;
                    }
                }
            }
            AuthorizationService::syncUserPermissions(
                (int) $user->id,
                (bool) $data['custom_permissions'],
                $ids
            );
        }
        ActivityLog::record(
            $user->organization_id,
            $actorId,
            'user.updated',
            'User "' . $data['name'] . '" was updated',
            'user',
            (int) $user->id
        );
    }

    public function setStatus(User $user, string $status, ?int $actorId = null): array
    {
        if ($user->organization_id !== null && $status !== 'active') {
            $role = $user->role();
            if ($role instanceof Role && $role->slug === 'owner') {
                $owners = User::countOwners((int) $user->organization_id, (int) $user->id);
                if ($owners < 1) {
                    return ['ok' => false, 'message' => 'The last owner cannot be deactivated.'];
                }
            }
        }

        User::updateById((int) $user->id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record(
            $user->organization_id,
            $actorId,
            $status === 'active' ? 'user.activated' : 'user.deactivated',
            'User "' . $user->name . '" was ' . ($status === 'active' ? 'activated' : 'deactivated'),
            'user',
            (int) $user->id
        );

        return ['ok' => true];
    }

    public function canAssignRole(string $actorSlug, int $roleId): bool
    {
        $role = Role::find($roleId);
        if (!$role instanceof Role || $role->scope !== 'organization') {
            return false;
        }

        if ($role->slug === 'super_admin') {
            return false;
        }

        if ($actorSlug !== 'owner' && $role->slug === 'owner') {
            return false;
        }

        return true;
    }
}
