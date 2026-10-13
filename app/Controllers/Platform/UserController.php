<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;

final class UserController extends Controller
{
    public function index(): void
    {
        $result = User::paginatePlatformUsers($this->page(), 15);

        $this->view('platform/users/index', [
            'title' => 'Platform users',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Platform users'],
            ],
            'users' => $result['items'],
            'pagination' => $result,
        ]);
    }

    public function create(): void
    {
        $this->view('platform/users/form', [
            'title' => 'Add platform user',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Platform users', 'url' => 'platform/users'],
                ['label' => 'Add'],
            ],
            'user' => null,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
        ]);
    }

    public function store(): void
    {
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only(['name', 'email', 'password', 'password_confirmation', 'status']);
        $validator = new Validator($data);

        if (!$validator->validate([
            'name' => 'required|max:160',
            'email' => 'required|email',
            'status' => 'required|in:active,inactive',
            'password' => 'required|min:' . $min . '|confirmed',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('platform/users/create');
        }

        if (User::emailExistsInOrganization((string) $data['email'], null)) {
            $this->withErrors(['email' => ['This email is already in use.']]);
            $this->redirect('platform/users/create');
        }

        $role = Role::findBySlug('super_admin');
        if (!$role instanceof Role) {
            flash('danger', 'The Super Admin role is missing.');
            $this->redirect('platform/users');
        }

        $now = date('Y-m-d H:i:s');
        User::create([
            'organization_id' => null,
            'role_id' => $role->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
            'job_title' => 'Platform Administrator',
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        flash('success', 'Platform user created.');
        $this->redirect('platform/users');
    }

    public function edit(string $id): void
    {
        $user = $this->platformUser($id);

        $this->view('platform/users/form', [
            'title' => 'Edit platform user',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Platform users', 'url' => 'platform/users'],
                ['label' => 'Edit'],
            ],
            'user' => $user,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
        ]);
    }

    public function update(string $id): void
    {
        $user = $this->platformUser($id);
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only(['name', 'email', 'password', 'password_confirmation', 'status']);

        $rules = [
            'name' => 'required|max:160',
            'email' => 'required|email',
            'status' => 'required|in:active,inactive',
        ];
        if (($data['password'] ?? '') !== '') {
            $rules['password'] = 'min:' . $min . '|confirmed';
        }

        $validator = new Validator($data);
        if (!$validator->validate($rules)) {
            $this->withErrors($validator->errors());
            $this->redirect('platform/users/' . $user->id . '/edit');
        }

        if (User::emailExistsInOrganization((string) $data['email'], $user->organization_id, (int) $user->id)) {
            $this->withErrors(['email' => ['This email is already in use.']]);
            $this->redirect('platform/users/' . $user->id . '/edit');
        }

        if ((int) $user->id === (int) Auth::id() && ($data['status'] ?? '') === 'inactive') {
            flash('danger', 'You cannot deactivate your own account.');
            $this->redirect('platform/users/' . $user->id . '/edit');
        }

        (new UserService())->update($user, [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'employee_code' => $user->employee_code,
            'status' => $data['status'],
            'password' => $data['password'] ?? '',
        ], null, $this->actor()->id);

        flash('success', 'Platform user updated.');
        $this->redirect('platform/users');
    }

    private function platformUser(string $id): User
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $user = User::find((int) $id);
        if (!$user instanceof User || $user->organization_id !== null) {
            Response::notFound();
        }

        return $user;
    }
}
