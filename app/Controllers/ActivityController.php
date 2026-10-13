<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Models\ActivityLog;
use App\Services\ProjectAccess;

final class ActivityController extends Controller
{
    public function index(): void
    {
        if (Auth::isSuperAdmin()) {
            $this->redirect('platform');
        }

        $this->authorize('activity.view');
        $role = Auth::role();
        if ($role !== null && $role->slug === 'client') {
            $this->redirect('dashboard');
        }

        $orgId = $this->tenantId();
        $canSeeAll = self::canSeeAllActivity();
        $viewerId = (int) (Auth::id() ?? 0);

        $userFilter = (int) Request::input('user', 0);
        if (!$canSeeAll) {
            // Employees cannot browse other people's full history.
            $userFilter = 0;
        }

        $page = max(1, (int) Request::input('page', 1));
        $perPage = 40;
        $result = ActivityLog::listForOrganization(
            $orgId,
            $userFilter > 0 ? $userFilter : null,
            $perPage,
            ($page - 1) * $perPage,
            $canSeeAll ? null : $viewerId
        );

        $this->view('activity/index', [
            'title' => 'Activity',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Activity'],
            ],
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'userId' => $userFilter,
            'employees' => $canSeeAll ? ActivityLog::employees($orgId) : [],
            'canSeeAll' => $canSeeAll,
            'scopedToSelf' => !$canSeeAll,
        ]);
    }

    /** Managers / owners see company-wide; employees see only related activity. */
    public static function canSeeAllActivity(): bool
    {
        if (ProjectAccess::seesAll() || Auth::can('users.view')) {
            return true;
        }

        $slug = Auth::role()?->slug ?? '';

        return in_array($slug, [
            'owner',
            'admin',
            'general_manager',
            'operations_manager',
            'manager',
            'project_manager',
        ], true);
    }
}
