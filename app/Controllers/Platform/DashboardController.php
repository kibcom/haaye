<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Organization;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $userCount = (int) Database::query('SELECT COUNT(*) FROM users')->fetchColumn();
        $platformUsers = (int) Database::query('SELECT COUNT(*) FROM users WHERE organization_id IS NULL')->fetchColumn();

        $this->view('dashboard/platform', [
            'title' => 'Platform',
            'breadcrumbs' => [['label' => 'Platform']],
            'organizationCount' => Organization::countAll(),
            'activeOrganizationCount' => Organization::countAll('active'),
            'userCount' => $userCount,
            'platformUserCount' => $platformUsers,
            'activity' => ActivityLog::recentPlatform(),
        ]);
    }
}
