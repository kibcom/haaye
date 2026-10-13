<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\UserService;

final class UserController extends Controller
{
    public function index(): void
    {
        $this->authorize('users.view');
        $orgId = $this->tenantId();
        $search = (string) Request::input('q', '');
        $status = (string) Request::input('status', '');

        \App\Services\Phase14DemoSetup::seed();
        \App\Services\LuminaDemoSetup::seed();

        $result = User::paginateForOrganization($orgId, $this->page(), 15, $search, $status);
        $organization = $this->currentOrganization();
        $demoPassword = \App\Services\Phase14DemoSetup::DEMO_PASSWORD;
        if ($organization && ($organization->slug ?? '') === \App\Services\LuminaDemoSetup::ORG_SLUG) {
            $demoPassword = \App\Services\LuminaDemoSetup::DEMO_PASSWORD;
        }

        $this->view('users/index', [
            'title' => 'People',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'People'],
            ],
            'users' => $result['items'],
            'pagination' => $result,
            'search' => $search,
            'status' => $status,
            'demoPassword' => $demoPassword,
            'organization' => $organization,
        ]);
    }

    public function create(): void
    {
        $this->authorize('users.create');
        AuthorizationService::applySchema();
        $this->view('users/form', $this->formViewData(null));
    }

    public function store(): void
    {
        $this->authorize('users.create');
        $orgId = $this->tenantId();
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only([
            'name', 'email', 'phone', 'job_title', 'employee_code',
            'role_id', 'status', 'joining_date', 'password', 'password_confirmation',
        ]);
        $data = array_merge($data, $this->accessPayload());

        $validator = new Validator($data);
        if (!$validator->validate([
            'name' => 'required|max:160',
            'email' => 'required|email|max:190',
            'role_id' => 'required',
            'status' => 'required|in:active,inactive',
            'password' => 'required|min:' . $min . '|confirmed',
        ])) {
            $this->withErrors($validator->errors());
            flash('danger', 'Could not add the person. Fix the highlighted fields and try again.');
            $this->redirect('users/create');
        }

        if (User::emailExistsInOrganization((string) $data['email'], $orgId)) {
            $this->withErrors(['email' => ['This email is already in use.']]);
            flash('danger', 'Could not add the person. That email is already in use.');
            $this->redirect('users/create');
        }

        $service = new UserService();
        if (!$service->canAssignRole((string) (Auth::role()?->slug ?? ''), (int) $data['role_id'])) {
            flash('danger', 'You cannot assign that role.');
            $this->redirect('users/create');
        }

        try {
            $service->create($orgId, $data, Request::file('profile_photo'), $this->actor()->id);
        } catch (\Throwable $e) {
            flash('danger', $e instanceof \RuntimeException ? $e->getMessage() : 'Could not add the person. Please try again.');
            $this->redirect('users/create');
        }

        flash('success', 'Person added successfully.');
        $this->redirect('users');
    }

    public function show(string $id): void
    {
        $this->authorize('users.view');
        AuthorizationService::applySchema();
        $user = $this->tenantUser($id);

        $isDemo = \App\Services\Phase14DemoSetup::isDemoUser((string) $user->email)
            || \App\Services\LuminaDemoSetup::isDemoUser((string) $user->email);
        $demoPassword = null;
        if ($isDemo) {
            $demoPassword = \App\Services\LuminaDemoSetup::isDemoUser((string) $user->email)
                ? \App\Services\LuminaDemoSetup::DEMO_PASSWORD
                : \App\Services\Phase14DemoSetup::DEMO_PASSWORD;
        }

        $this->view('users/show', [
            'title' => $user->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'People', 'url' => 'users'],
                ['label' => $user->name],
            ],
            'user' => $user,
            'permissions' => Permission::groupedForRole((int) $user->role_id),
            'effectivePermissions' => $this->groupedSlugs(AuthorizationService::permissionsForUser($user)),
            'customPermissions' => (int) $user->custom_permissions === 1,
            'isDemoUser' => $isDemo,
            'demoPassword' => $demoPassword,
            'organization' => $this->currentOrganization(),
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorize('users.update');
        AuthorizationService::applySchema();
        $user = $this->tenantUser($id);
        $this->view('users/form', $this->formViewData($user));
    }

    public function update(string $id): void
    {
        $this->authorize('users.update');
        $user = $this->tenantUser($id);
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only([
            'name', 'email', 'phone', 'job_title', 'employee_code',
            'role_id', 'status', 'joining_date', 'password', 'password_confirmation',
        ]);
        $data = array_merge($data, $this->accessPayload());

        $rules = [
            'name' => 'required|max:160',
            'email' => 'required|email|max:190',
            'role_id' => 'required',
            'status' => 'required|in:active,inactive',
        ];

        if (($data['password'] ?? '') !== '') {
            $this->authorize('users.reset_password');
            $rules['password'] = 'min:' . $min . '|confirmed';
        }

        $validator = new Validator($data);
        if (!$validator->validate($rules)) {
            $this->withErrors($validator->errors());
            flash('danger', 'Could not update the person. Fix the highlighted fields and try again.');
            $this->redirect('users/' . $user->id . '/edit');
        }

        if (User::emailExistsInOrganization((string) $data['email'], $user->organization_id, (int) $user->id)) {
            $this->withErrors(['email' => ['This email is already in use.']]);
            flash('danger', 'Could not update the person. That email is already in use.');
            $this->redirect('users/' . $user->id . '/edit');
        }

        $service = new UserService();
        if (!$service->canAssignRole((string) (Auth::role()?->slug ?? ''), (int) $data['role_id'])) {
            flash('danger', 'You cannot assign that role.');
            $this->redirect('users/' . $user->id . '/edit');
        }

        if ((int) $user->id === (int) Auth::id() && ($data['status'] ?? '') === 'inactive') {
            flash('danger', 'You cannot deactivate your own account.');
            $this->redirect('users/' . $user->id . '/edit');
        }

        try {
            $service->update($user, $data, Request::file('profile_photo'), $this->actor()->id);
        } catch (\Throwable $e) {
            flash('danger', $e instanceof \RuntimeException ? $e->getMessage() : 'Could not update the person. Please try again.');
            $this->redirect('users/' . $user->id . '/edit');
        }

        if ((int) $user->id === (int) Auth::id()) {
            Auth::reset();
        }

        flash('success', 'Person updated successfully.');
        $this->redirect('users/' . $user->id);
    }

    public function activate(string $id): void
    {
        $this->changeStatus($id, 'active');
    }

    public function deactivate(string $id): void
    {
        $this->changeStatus($id, 'inactive');
    }

    private function changeStatus(string $id, string $status): void
    {
        $this->authorize('users.activate');
        $user = $this->tenantUser($id);

        if ((int) $user->id === (int) Auth::id()) {
            flash('danger', 'You cannot change the status of your own account.');
            $this->redirect('users/' . $user->id);
        }

        $result = (new UserService())->setStatus($user, $status, $this->actor()->id);
        flash($result['ok'] ? 'success' : 'danger', $result['ok']
            ? 'User status has been updated.'
            : $result['message']);
        $this->redirect('users/' . $user->id);
    }

    private function tenantUser(string $id): User
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $user = User::findInOrganization((int) $id, $this->tenantId());
        if (!$user instanceof User) {
            Response::notFound();
        }

        return $user;
    }

    private function assignableRoles(?User $user = null): array
    {
        $roles = Role::assignableFor((string) (Auth::role()?->slug ?? ''));
        $keepOwner = $user !== null && ($user->role_slug ?? '') === 'owner';

        return array_values(array_filter($roles, static function (Role $role) use ($keepOwner): bool {
            return $keepOwner || $role->slug !== 'owner';
        }));
    }

    private function canEditAccess(): bool
    {
        return Auth::can('roles.assign') || Auth::can('roles.update');
    }

    private function accessPayload(): array
    {
        if (!$this->canEditAccess() || Request::input('permissions_submitted') !== '1') {
            return [];
        }

        $ids = Request::input('permission_ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }

        return [
            'custom_permissions' => Request::input('custom_permissions') === '1',
            'permission_ids' => $ids,
        ];
    }

    private function formViewData(?User $user): array
    {
        $roles = $this->assignableRoles($user);
        $canEditAccess = $this->canEditAccess();
        $rolePermissionMap = [];
        foreach ($roles as $role) {
            $rolePermissionMap[(string) $role->id] = AuthorizationService::permissionIdsForRole((int) $role->id);
        }

        $customEnabled = $user instanceof User && (int) $user->custom_permissions === 1;
        if ($user instanceof User && $customEnabled) {
            $selected = AuthorizationService::permissionIdsForUser((int) $user->id);
        } elseif ($user instanceof User) {
            $selected = AuthorizationService::permissionIdsForRole((int) $user->role_id);
        } else {
            $firstRole = $roles[0] ?? null;
            $selected = $firstRole instanceof Role
                ? AuthorizationService::permissionIdsForRole((int) $firstRole->id)
                : [];
        }

        return [
            'title' => $user instanceof User ? 'Edit ' . $user->name : 'Add person',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'People', 'url' => 'users'],
                ['label' => $user instanceof User ? 'Edit' : 'Add person'],
            ],
            'user' => $user,
            'roles' => $roles,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
            'canEditAccess' => $canEditAccess,
            'permissionGroups' => $canEditAccess ? Permission::groupedAssignable() : [],
            'selectedPermissionIds' => $selected,
            'customEnabled' => $customEnabled,
            'rolePermissionMap' => $rolePermissionMap,
        ];
    }

    /** @param list<string> $slugs */
    private function groupedSlugs(array $slugs): array
    {
        $lookup = array_fill_keys($slugs, true);
        $grouped = [];
        foreach (Permission::groupedAssignable() as $group => $items) {
            foreach ($items as $item) {
                if (!empty($lookup[$item['slug']])) {
                    $grouped[$group][] = $item;
                }
            }
        }

        return $grouped;
    }
}
