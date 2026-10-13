<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;

final class Auth
{
    private static ?User $user = null;
    private static bool $resolved = false;
    private static ?array $permissions = null;

    public static function check(): bool
    {
        return self::user() instanceof User;
    }

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        $userId = Session::get('user_id');
        if (!is_numeric($userId)) {
            return null;
        }

        $user = User::find((int) $userId);
        if (!$user instanceof User || !$user->isActive()) {
            self::clear();
            return null;
        }

        if ($user->organization_id !== null) {
            $organization = Organization::find((int) $user->organization_id);
            if (!$organization instanceof Organization || !$organization->isActive()) {
                self::clear();
                return null;
            }
        }

        self::$user = $user;
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user()?->id;
    }

    public static function organizationId(): ?int
    {
        return self::user()?->organization_id;
    }

    public static function role(): ?Role
    {
        $user = self::user();
        if (!$user instanceof User) {
            return null;
        }

        return Role::find($user->role_id);
    }

    public static function isSuperAdmin(): bool
    {
        $role = self::role();
        return $role instanceof Role && $role->slug === 'super_admin';
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        if (self::$permissions === null) {
            $user = self::user();
            self::$permissions = $user instanceof User
                ? AuthorizationService::permissionsForUser($user)
                : [];
        }

        return in_array($permission, self::$permissions, true);
    }

    public static function login(User $user): void
    {
        Session::regenerate();
        Session::set('user_id', $user->id);
        Session::set('organization_id', $user->organization_id);
        self::$user = $user;
        self::$resolved = true;
        self::$permissions = null;
    }

    public static function logout(): void
    {
        self::clear();
        Session::destroy();
    }

    public static function clear(): void
    {
        Session::forget('user_id');
        Session::forget('organization_id');
        self::$user = null;
        self::$resolved = true;
        self::$permissions = null;
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$resolved = false;
        self::$permissions = null;
    }
}
