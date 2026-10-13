<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Models\User;

final class AuthService
{
    public function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = Request::ip();

        if ($this->isLocked($email, $ip)) {
            Logger::warning('Locked login attempt for ' . $email . ' from ' . $ip);
            return ['ok' => false, 'message' => 'Too many login attempts. Please try again in 15 minutes.'];
        }

        $user = $this->matchingAccount($email, $password);
        $valid = $user instanceof User;

        $this->recordAttempt($email, $ip, $valid);

        if (!$valid) {
            Logger::warning('Failed login for ' . $email . ' from ' . $ip);
            ActivityLog::record(null, null, 'auth.login_failed', 'Failed login attempt for ' . $email);
            return ['ok' => false, 'message' => 'These credentials do not match our records.'];
        }

        Auth::login($user);
        User::updateById((int) $user->id, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record($user->organization_id, $user->id, 'auth.login', $user->name . ' signed in');
        return ['ok' => true, 'user' => $user];
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user instanceof User) {
            ActivityLog::record($user->organization_id, $user->id, 'auth.logout', $user->name . ' signed out');
        }

        Auth::logout();
    }

    public function switchAccount(User $current, int $targetUserId): array
    {
        $target = User::find($targetUserId);
        if (!$target instanceof User || strtolower($target->email) !== strtolower($current->email)) {
            return ['ok' => false, 'message' => 'That company is not available on this account.'];
        }

        if (!$target->isActive()) {
            return ['ok' => false, 'message' => 'This account is inactive. Contact your administrator.'];
        }

        if ($target->organization_id !== null) {
            $organization = $target->organization();
            if ($organization === null || !$organization->isActive()) {
                return ['ok' => false, 'message' => 'This organization is currently unavailable.'];
            }
        }

        if ((int) $target->id === (int) $current->id) {
            return ['ok' => true, 'user' => $current];
        }

        Auth::login($target);
        User::updateById((int) $target->id, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => Request::ip(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record($target->organization_id, $target->id, 'auth.workspace_switched', $target->name . ' switched company');

        return ['ok' => true, 'user' => $target];
    }

    private function matchingAccount(string $email, string $password): ?User
    {
        foreach (User::findAllByEmail($email) as $account) {
            if (!password_verify($password, $account->password_hash) || !$account->isActive()) {
                continue;
            }

            if ($account->organization_id !== null) {
                $organization = $account->organization();
                if ($organization === null || !$organization->isActive()) {
                    continue;
                }
            }

            return $account;
        }

        return null;
    }

    public function requestReset(string $email): void
    {
        $email = strtolower(trim($email));
        $user = User::findByEmail($email);

        if (!$user instanceof User || !$user->isActive()) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $minutes = (int) Config::get('auth.password_reset_minutes', 60);

        Database::query(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [$user->id, $hash, date('Y-m-d H:i:s', time() + ($minutes * 60)), date('Y-m-d H:i:s')]
        );

        $link = base_url('reset-password?token=' . $token);
        Mailer::send(
            $user->email,
            'Reset your ' . app_name() . ' password',
            "Hello {$user->name},\n\nReset your password using this link (valid for {$minutes} minutes):\n{$link}\n"
        );

        ActivityLog::record($user->organization_id, $user->id, 'auth.password_reset_requested', 'Password reset requested');
    }

    public function resetPassword(string $token, string $password): array
    {
        $hash = hash('sha256', $token);
        $row = Database::query(
            'SELECT * FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ?
             ORDER BY id DESC LIMIT 1',
            [$hash, date('Y-m-d H:i:s')]
        )->fetch();

        if (!$row) {
            return ['ok' => false, 'message' => 'This reset link is invalid or has expired.'];
        }

        $user = User::find((int) $row['user_id']);
        if (!$user instanceof User || !$user->isActive()) {
            return ['ok' => false, 'message' => 'This reset link is invalid or has expired.'];
        }

        User::updateById((int) $user->id, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Database::query('UPDATE password_resets SET used_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $row['id']]);
        ActivityLog::record($user->organization_id, $user->id, 'auth.password_reset', 'Password was reset');

        return ['ok' => true];
    }

    private function isLocked(string $email, string $ip): bool
    {
        $max = (int) Config::get('auth.max_attempts', 5);
        $window = (int) Config::get('auth.attempt_window_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - ($window * 60));

        $emailFails = (int) Database::query(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at >= ?',
            [$email, $since]
        )->fetchColumn();

        $ipFails = (int) Database::query(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at >= ?',
            [$ip, $since]
        )->fetchColumn();

        return $emailFails >= $max || $ipFails >= $max;
    }

    private function recordAttempt(string $email, string $ip, bool $success): void
    {
        Database::query(
            'INSERT INTO login_attempts (email, ip_address, success, attempted_at) VALUES (?, ?, ?, ?)',
            [$email, $ip, $success ? 1 : 0, date('Y-m-d H:i:s')]
        );
    }
}
