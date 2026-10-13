<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        if ($layout === 'layouts/app') {
            $this->pulseDailyTaskNotifications();
        }
        $data['alert'] = Session::pullFlash('alert');
        View::render($template, $data, $layout);
        Session::forget('_errors');
        Session::forget('_old');
    }

    protected function pulseDailyTaskNotifications(): void
    {
        $user = Auth::user();
        if (!$user instanceof User || $user->organization_id === null) {
            return;
        }
        $today = $this->organizationToday($user);
        $key = 'wf_due_reminders_' . $today . '_' . (int) $user->id;
        if (Session::get($key)) {
            return;
        }
        $organizationId = (int) $user->organization_id;
        $userId = (int) $user->id;
        $role = Auth::role();
        if ($role !== null && $role->slug === 'client') {
            $clientId = (int) ($user->client_id ?? 0);
            if ($clientId > 0) {
                NotificationService::remindPayments($organizationId, $userId, $today, null, $clientId, true);
            }
            Session::set($key, '1');
            return;
        }
        NotificationService::remindDueToday($organizationId, $userId, $today);
        if (Auth::can('invoices.view') || Auth::can('finance.view')) {
            NotificationService::remindPayments($organizationId, $userId, $today, \App\Services\ProjectAccess::restrictToUserId());
        }
        Session::set($key, '1');
    }

    private function organizationToday(User $user): string
    {
        $organization = $user->organization_id !== null ? Organization::find((int) $user->organization_id) : null;
        $tzName = $organization instanceof Organization ? $organization->timezone : 'UTC';
        try {
            $zone = new \DateTimeZone($tzName !== '' ? $tzName : 'UTC');
        } catch (\Exception) {
            $zone = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $zone))->format('Y-m-d');
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }

    protected function back(): void
    {
        Response::back();
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            Response::forbidden();
        }
    }

    protected function tenantId(): int
    {
        $id = Auth::organizationId();
        if ($id === null) {
            Response::forbidden();
        }

        return $id;
    }

    protected function currentOrganization(): ?Organization
    {
        $id = Auth::organizationId();
        return $id !== null ? Organization::find($id) : null;
    }

    protected function actor(): User
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            Response::redirect('login');
        }

        return $user;
    }

    protected function withErrors(array $errors, array $old = []): void
    {
        Session::flash('_errors', $errors);
        Session::set('_old', $old !== [] ? $old : Request::all());
    }

    protected function clearOld(): void
    {
        Session::forget('_old');
        Session::forget('_errors');
    }

    protected function page(): int
    {
        $page = (int) Request::input('page', 1);
        return max(1, $page);
    }
}
