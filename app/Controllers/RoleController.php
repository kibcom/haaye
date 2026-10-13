<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuthorizationService;

final class RoleController extends Controller
{
    public function index(): void
    {
        $this->authorize('roles.view');
        \App\Services\PermissionCatalog::sync(true);

        $roles = array_values(array_filter(
            Role::organizationRoles(),
            static fn (Role $role): bool => $role->slug !== 'owner'
        ));

        $this->view('roles/index', [
            'title' => 'Roles',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Settings', 'url' => 'settings'],
                ['label' => 'Roles'],
            ],
            'roles' => $roles,
            'roleFeatureAccess' => $this->featureAccessByRole($roles),
            'canEdit' => Auth::can('roles.update') || Auth::can('roles.assign'),
        ]);
    }

    public function show(string $id): void
    {
        $this->authorize('roles.view');
        \App\Services\PermissionCatalog::sync(false);
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $role = Role::find((int) $id);
        if (!$role instanceof Role || $role->scope !== 'organization') {
            Response::notFound();
        }

        $this->view('roles/show', [
            'title' => $role->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Settings', 'url' => 'settings'],
                ['label' => 'Roles', 'url' => 'roles'],
                ['label' => $role->name],
            ],
            'role' => $role,
            'permissions' => Permission::groupedForRole((int) $role->id),
            'canEdit' => Auth::can('roles.update') || Auth::can('roles.assign'),
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorizeRoleEdit();
        \App\Services\PermissionCatalog::sync(false);
        $role = $this->organizationRole($id);

        $this->view('roles/edit', [
            'title' => 'Edit ' . $role->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Settings', 'url' => 'settings'],
                ['label' => 'Roles', 'url' => 'roles'],
                ['label' => $role->name, 'url' => 'roles/' . $role->id],
                ['label' => 'Access'],
            ],
            'role' => $role,
            'permissionGroups' => Permission::groupedAssignable(),
            'selectedPermissionIds' => AuthorizationService::permissionIdsForRole((int) $role->id),
            'customEnabled' => true,
            'canCustomize' => false,
        ]);
    }

    public function update(string $id): void
    {
        $this->authorizeRoleEdit();
        AuthorizationService::applySchema();
        $role = $this->organizationRole($id);

        $ids = Request::input('permission_ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }

        AuthorizationService::syncRolePermissions((int) $role->id, $ids);
        ActivityLog::record(
            $this->tenantId(),
            $this->actor()->id,
            'role.updated',
            'Role "' . $role->name . '" access was updated',
            'role',
            (int) $role->id
        );

        flash('success', 'Role access saved. Selected boxes stay until you change them again — seed defaults will not overwrite this role.');
        $this->redirect('roles/' . $role->id);
    }

    private function authorizeRoleEdit(): void
    {
        if (!Auth::can('roles.update') && !Auth::can('roles.assign')) {
            Response::forbidden();
        }
    }

    private function organizationRole(string $id): Role
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $role = Role::find((int) $id);
        if (!$role instanceof Role || $role->scope !== 'organization') {
            Response::notFound();
        }

        return $role;
    }

    /**
     * @param list<Role> $roles
     * @return array<int, array{clients:bool,clientsAll:bool,workflow:bool,grids:bool,manage:bool,customized:bool}>
     */
    private function featureAccessByRole(array $roles): array
    {
        $out = [];
        foreach ($roles as $role) {
            $id = (int) $role->id;
            $rows = Database::query(
                "SELECT p.slug
                 FROM permissions p
                 INNER JOIN role_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = ? AND p.slug IN (
                    'clients.view', 'clients.view_all', 'leads.view_all',
                    'work_flow.view', 'work_flow.assign', 'work_flow.manage'
                 )",
                [$id]
            )->fetchAll();
            $slugs = array_column($rows, 'slug');
            $custom = Database::query(
                'SELECT access_customized FROM roles WHERE id = ? LIMIT 1',
                [$id]
            )->fetch();
            $out[$id] = [
                'clients' => in_array('clients.view', $slugs, true),
                'clientsAll' => in_array('clients.view_all', $slugs, true) || in_array('leads.view_all', $slugs, true),
                'workflow' => in_array('work_flow.view', $slugs, true),
                'grids' => in_array('work_flow.assign', $slugs, true) || in_array('work_flow.manage', $slugs, true),
                'manage' => in_array('work_flow.manage', $slugs, true),
                'customized' => $custom !== false && (int) ($custom['access_customized'] ?? 0) === 1,
            ];
        }

        return $out;
    }
}
