<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContentGrid;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkFlowRole;
use App\Models\WorkFlowTask;
use App\Services\ContentGridService;
use App\Services\Phase10Setup;
use App\Services\Phase11Setup;
use App\Services\Phase12Setup;
use App\Services\ProjectAccess;
use App\Services\WorkFlowService;

final class CrmWorkflowController extends Controller
{
    public function index(): void
    {
        $this->authorize('work_flow.view');
        Phase10Setup::seedPermissions();
        Phase10Setup::seedPipelineRoles();
        Phase11Setup::applySchema();
        Phase12Setup::applySchema();

        $orgId = $this->tenantId();
        $onlyUserId = ProjectAccess::restrictToUserId();
        $projectId = (int) Request::input('project', 0);
        $status = (string) Request::input('status', '');
        if (!in_array($status, WorkFlowService::STATUSES, true)) {
            $status = '';
        }

        $roles = WorkFlowRole::listFor($orgId);
        $tasks = WorkFlowTask::listFor(
            $orgId,
            $onlyUserId,
            $projectId > 0 ? $projectId : null,
            $status !== '' ? $status : null
        );
        $allTasks = WorkFlowTask::listFor($orgId, $onlyUserId);
        $grids = ContentGrid::listFor($orgId);
        $taskStatusCounts = array_fill_keys(WorkFlowService::STATUSES, 0);
        foreach ($allTasks as $task) {
            if (isset($taskStatusCounts[$task->status])) {
                $taskStatusCounts[$task->status]++;
            }
        }
        $activeRoles = 0;
        foreach ($roles as $role) {
            if ((int) $role->is_active === 1) {
                $activeRoles++;
            }
        }

        $this->view('work-flow/index', [
            'title' => 'Workflow',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Workflow'],
            ],
            'roles' => $roles,
            'tasks' => $tasks,
            'contentGrids' => array_slice($grids, 0, 6),
            'projects' => Project::optionsVisible($orgId, $onlyUserId),
            'people' => User::listAssignable($orgId),
            'status' => $status,
            'projectId' => $projectId,
            'statusLabels' => WorkFlowService::statusLabels(),
            'canManage' => (new WorkFlowService())->canManage(),
            'canAssign' => (new WorkFlowService())->canAssign(),
            'canViewGrids' => (new ContentGridService())->canView(),
            'canManageGrids' => (new ContentGridService())->canManage(),
            'stats' => [
                'roles' => count($roles),
                'active_roles' => $activeRoles,
                'tasks' => count($allTasks),
                'task_status' => $taskStatusCounts,
                'grids' => count($grids),
            ],
        ]);
    }

    public function storeRole(): void
    {
        $this->authorize('work_flow.manage');
        try {
            (new WorkFlowService())->createRole($this->tenantId(), Request::all(), (int) $this->actor()->id);
            flash('success', 'Workflow role added.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    public function updateRole(string $id): void
    {
        $this->authorize('work_flow.manage');
        $role = $this->role($id);
        try {
            (new WorkFlowService())->updateRole($role, Request::all(), (int) $this->actor()->id);
            flash('success', 'Workflow role updated.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    public function destroyRole(string $id): void
    {
        $this->authorize('work_flow.manage');
        $role = $this->role($id);
        try {
            (new WorkFlowService())->deleteRole($role, (int) $this->actor()->id);
            flash('success', 'Workflow role removed.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    public function storeTask(): void
    {
        $this->authorize('work_flow.assign');
        try {
            (new WorkFlowService())->createTask($this->tenantId(), Request::all(), (int) $this->actor()->id);
            flash('success', 'Workflow task added.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    public function updateTask(string $id): void
    {
        $task = $this->visibleTask($id);
        $service = new WorkFlowService();
        if (!$service->canAssign() && (int) $task->assignee_user_id !== (int) Auth::id()) {
            Response::forbidden();
        }

        try {
            if ($service->canAssign()) {
                $service->updateTask($task, Request::all(), (int) $this->actor()->id);
            } else {
                $service->updateStatus($task, (string) Request::input('status', $task->status), (int) $this->actor()->id);
            }
            flash('success', 'Workflow task updated.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    public function status(string $id): void
    {
        $task = $this->visibleTask($id);
        $service = new WorkFlowService();
        $isAssignee = (int) $task->assignee_user_id === (int) Auth::id();
        if (!$service->canAssign() && !$isAssignee && !$service->canManage()) {
            Response::forbidden();
        }

        try {
            $service->updateStatus($task, (string) Request::input('status', ''), (int) $this->actor()->id);
            flash('success', 'Task status updated.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    public function destroyTask(string $id): void
    {
        $this->authorize('work_flow.manage');
        $task = $this->visibleTask($id);
        try {
            (new WorkFlowService())->deleteTask($task, (int) $this->actor()->id);
            flash('success', 'Workflow task removed.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow');
    }

    private function role(string $id): WorkFlowRole
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $role = WorkFlowRole::findInOrganization((int) $id, $this->tenantId());
        if (!$role instanceof WorkFlowRole) {
            Response::notFound();
        }

        return $role;
    }

    private function visibleTask(string $id): WorkFlowTask
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $task = WorkFlowTask::findInOrganization((int) $id, $this->tenantId());
        if (!$task instanceof WorkFlowTask || !(new WorkFlowService())->canViewTask($task)) {
            Response::notFound();
        }

        return $task;
    }
}
