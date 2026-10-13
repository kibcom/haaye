<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Services\EmployeeWorkspaceService;
use App\Services\Phase14Setup;

final class MyWorkController extends Controller
{
    public function index(): void
    {
        Phase14Setup::applySchema();
        Phase14Setup::seedPermissions();

        if (!Auth::can('workspace.view') && !Auth::can('tasks.view')) {
            $this->authorize('workspace.view');
        }

        $orgId = $this->tenantId();
        $userId = (int) $this->actor()->id;
        $data = (new EmployeeWorkspaceService())->forUser($orgId, $userId);

        $this->view('my-work/index', [
            'title' => 'My Work',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'My Work'],
            ],
            'counts' => $data['counts'],
            'dueToday' => $data['due_today'],
            'overdue' => $data['overdue'],
            'inProgress' => $data['in_progress'],
            'changesRequested' => $data['changes_requested'],
            'submitted' => $data['submitted'],
            'upcoming' => $data['upcoming'],
        ]);
    }
}
