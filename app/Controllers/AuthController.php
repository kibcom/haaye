<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Validator;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', [
            'title' => 'Sign in',
        ], 'layouts/login');
    }

    public function login(): void
    {
        $data = Request::only(['email', 'password']);
        $validator = new Validator($data);

        if (!$validator->validate([
            'email' => 'required|email',
            'password' => 'required',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('login');
        }

        $result = (new AuthService())->attempt((string) $data['email'], (string) $data['password']);
        if (!$result['ok']) {
            flash('danger', $result['message']);
            $this->withErrors([], ['email' => $data['email']]);
            $this->redirect('login');
        }

        if (Request::input('remember') === '1') {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + (60 * 60 * 24 * 30),
                'path' => $params['path'],
                'domain' => (string) ($params['domain'] ?? ''),
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }

        $this->clearOld();
        $this->redirect(Auth::isSuperAdmin() ? 'platform' : 'dashboard');
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        $this->redirect('login');
    }

    public function switchWorkspace(): void
    {
        $result = (new AuthService())->switchAccount($this->actor(), (int) Request::input('account', 0));
        if (!$result['ok']) {
            flash('danger', $result['message']);
            $this->redirect('dashboard');
        }

        $this->redirect(Auth::isSuperAdmin() ? 'platform' : 'dashboard');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot-password', [
            'title' => 'Reset password',
        ], 'layouts/auth');
    }

    public function forgot(): void
    {
        $data = Request::only(['email']);
        $validator = new Validator($data);
        if (!$validator->validate(['email' => 'required|email'])) {
            $this->withErrors($validator->errors());
            $this->redirect('forgot-password');
        }

        (new AuthService())->requestReset((string) $data['email']);
        flash('success', 'If an account exists for that email, password reset instructions have been sent.');
        $this->redirect('forgot-password');
    }

    public function showReset(): void
    {
        $token = (string) Request::input('token', '');
        if ($token === '') {
            flash('danger', 'This reset link is invalid or has expired.');
            $this->redirect('forgot-password');
        }

        $this->view('auth/reset-password', [
            'title' => 'Choose a new password',
            'token' => $token,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
        ], 'layouts/auth');
    }

    public function reset(): void
    {
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only(['token', 'password', 'password_confirmation']);
        $validator = new Validator($data);

        if (!$validator->validate([
            'token' => 'required',
            'password' => 'required|min:' . $min . '|confirmed',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('reset-password?token=' . urlencode((string) $data['token']));
        }

        $result = (new AuthService())->resetPassword((string) $data['token'], (string) $data['password']);
        if (!$result['ok']) {
            flash('danger', $result['message']);
            $this->redirect('forgot-password');
        }

        flash('success', 'Your password has been updated. You can sign in now.');
        $this->redirect('login');
    }
}
