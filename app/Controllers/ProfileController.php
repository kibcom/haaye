<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\UserService;

final class ProfileController extends Controller
{
    public function edit(): void
    {
        $this->authorize('profile.view');
        $user = $this->actor();

        $this->view('profile/edit', [
            'title' => 'My profile',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'My profile'],
            ],
            'user' => $user,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
        ]);
    }

    public function update(): void
    {
        $this->authorize('profile.update');
        $user = $this->actor();
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only(['name', 'phone', 'current_password', 'password', 'password_confirmation']);

        $rules = [
            'name' => 'required|max:160',
            'phone' => 'max:50',
        ];

        if (($data['password'] ?? '') !== '') {
            $rules['current_password'] = 'required';
            $rules['password'] = 'required|min:' . $min . '|confirmed';
        }

        $validator = new Validator($data);
        if (!$validator->validate($rules)) {
            $this->withErrors($validator->errors());
            $this->redirect('profile');
        }

        if (($data['password'] ?? '') !== '' && !password_verify((string) $data['current_password'], $user->password_hash)) {
            $this->withErrors(['current_password' => ['Current password is incorrect.']]);
            $this->redirect('profile');
        }

        try {
            (new UserService())->update($user, [
                'name' => $data['name'],
                'email' => $user->email,
                'phone' => $data['phone'] ?? '',
                'job_title' => $user->job_title,
                'employee_code' => $user->employee_code,
                'password' => $data['password'] ?? '',
            ], Request::file('profile_photo'), $user->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('profile');
        }

        if (($data['password'] ?? '') !== '') {
            ActivityLog::record($user->organization_id, $user->id, 'auth.password_changed', 'Password was changed');
        }

        flash('success', 'Your profile has been updated.');
        $this->redirect('profile');
    }
}
