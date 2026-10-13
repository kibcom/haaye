<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\Deliverable;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ProjectAccess;
use App\Services\ProjectService;
use App\Services\TaskAccess;
use App\Services\DeliverableAccess;

final class ProjectController extends Controller
{
    public function index(): void
    {
        $this->authorize('projects.view');
        $orgId = $this->tenantId();
        ProjectStatus::ensureDefaults($orgId);

        $search = (string) Request::input('q', '');
        $status = (string) Request::input('status', '');
        $priority = (string) Request::input('priority', '');
        $paymentStatus = (string) Request::input('payment_status', '');
        $allowedPayments = ['unpaid', 'partial', 'paid', 'not_required'];
        if (!in_array($paymentStatus, $allowedPayments, true)) {
            $paymentStatus = '';
        }
        $clientId = (int) Request::input('client_id', 0);

        $result = Project::paginate(
            $orgId,
            $this->page(),
            15,
            $search,
            $status,
            $priority,
            $clientId > 0 ? $clientId : null,
            ProjectAccess::restrictToUserId(),
            $paymentStatus !== '' ? $paymentStatus : null
        );

        $this->view('projects/index', [
            'title' => 'Projects',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects'],
            ],
            'projects' => $result['items'],
            'pagination' => $result,
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'paymentStatus' => $paymentStatus,
            'clientId' => $clientId,
            'clients' => Client::optionsForOrganization($orgId),
            'statuses' => ProjectStatus::forOrganization($orgId),
        ]);
    }

    public function create(): void
    {
        $this->authorize('projects.create');
        $orgId = $this->tenantId();
        ProjectStatus::ensureDefaults($orgId);
        $clientId = (int) Request::input('client', Request::input('client_id', 0));
        $prefillClient = null;
        if ($clientId > 0) {
            $prefillClient = Client::findInOrganization($clientId, $orgId);
            if (!$prefillClient instanceof Client) {
                $clientId = 0;
            }
        }

        $this->view('projects/form', [
            'title' => 'New project',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects', 'url' => 'projects'],
                ['label' => 'New project'],
            ],
            'project' => null,
            'clients' => Client::optionsForOrganization($orgId),
            'statuses' => ProjectStatus::forOrganization($orgId),
            'people' => User::listAssignable($orgId),
            'prefillClient' => $clientId,
            'prefillClientModel' => $prefillClient,
        ]);
    }

    public function store(): void
    {
        $this->authorize('projects.create');
        $data = $this->formData();
        $clientId = (int) ($data['client_id'] ?? 0);
        $back = $clientId > 0 ? 'projects/create?client=' . $clientId : 'projects/create';
        if (!$this->validateProject($data, $back)) {
            return;
        }

        try {
            $project = (new ProjectService())->create($this->tenantId(), $data, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect($back);
        }

        flash('success', 'Project has been created.');
        $this->redirect('projects/' . $project->id);
    }

    public function show(string $id): void
    {
        $project = $this->visibleProject($id);
        $orgId = (int) $project->organization_id;

        $autoTracking = false;
        $runningTimer = null;
        if (Auth::can('time.create') && \App\Services\FeatureService::allows('time')) {
            $tracked = (new \App\Services\TimeService())->autoTrack($orgId, [
                'project_id' => (int) $project->id,
                'task_id' => 0,
                'billable' => '1',
            ], (int) Auth::id());
            $autoTracking = $tracked instanceof \App\Models\TimeEntry
                && (int) $tracked->project_id === (int) $project->id;
            $runningTimer = $tracked;
        } elseif (Auth::can('time.view')) {
            $runningTimer = \App\Models\TimeEntry::runningFor($orgId, (int) Auth::id());
        }

        $this->view('projects/show', [
            'title' => $project->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects', 'url' => 'projects'],
                ['label' => $project->name],
            ],
            'project' => $project,
            'members' => ProjectMember::forProject((int) $project->id, $orgId),
            'milestones' => Milestone::forProject((int) $project->id, $orgId),
            'people' => User::listAssignable($orgId),
            'activity' => ActivityLog::recentForEntity($orgId, 'project', (int) $project->id),
            'canEdit' => ProjectAccess::canEdit($project),
            'canManageMembers' => ProjectAccess::canManageMembers($project),
            'canManageMilestones' => ProjectAccess::canManageMilestones($project),
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
            'tasks' => Auth::can('tasks.view') ? Task::forProject((int) $project->id, $orgId) : [],
            'taskCount' => Auth::can('tasks.view') ? Task::countForProject((int) $project->id, $orgId) : 0,
            'canCreateTask' => TaskAccess::canCreate($project),
            'deliverables' => (Auth::can('deliverables.view')) ? Deliverable::forProject((int) $project->id, $orgId) : [],
            'bookings' => (Auth::can('bookings.view') && \App\Services\FeatureService::allows('bookings'))
                ? Booking::forProject((int) $project->id, $orgId)
                : [],
            'canCreateDeliverable' => DeliverableAccess::canCreate($project),
            'finance' => Auth::can('finance.view') ? array_merge(
                Invoice::projectTotals($orgId, (int) $project->id),
                ['expenses' => Expense::approvedForProject($orgId, (int) $project->id)]
            ) : null,
            'runningTimer' => $runningTimer,
            'autoTracking' => $autoTracking,
            'pipelineRuns' => (Auth::can('pipeline.view') && \App\Services\FeatureService::allows('pipeline'))
                ? \App\Models\WorkflowRun::forProject((int) $project->id, $orgId)
                : [],
            'pipelineTemplates' => (Auth::can('pipeline.manage') && \App\Services\FeatureService::allows('pipeline'))
                ? \App\Models\WorkflowTemplate::forOrganization($orgId, true)
                : [],
            'pipelineTemplateStages' => (Auth::can('pipeline.manage') && \App\Services\FeatureService::allows('pipeline'))
                ? $this->pipelineStagesByTemplate($orgId)
                : [],
            'canStartPipeline' => Auth::can('pipeline.manage') && \App\Services\FeatureService::allows('pipeline'),
        ]);
    }

    /** @return array<int, list<\App\Models\WorkflowTemplateStage>> */
    private function pipelineStagesByTemplate(int $orgId): array
    {
        $map = [];
        foreach (\App\Models\WorkflowTemplate::forOrganization($orgId, true) as $tpl) {
            $map[(int) $tpl->id] = \App\Models\WorkflowTemplateStage::forTemplate((int) $tpl->id, $orgId, true);
        }

        return $map;
    }

    public function edit(string $id): void
    {
        $project = $this->editableProject($id);
        $orgId = $this->tenantId();

        $this->view('projects/form', [
            'title' => 'Edit ' . $project->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects', 'url' => 'projects'],
                ['label' => $project->name, 'url' => 'projects/' . $project->id],
                ['label' => 'Edit'],
            ],
            'project' => $project,
            'clients' => Client::optionsForOrganization($orgId),
            'statuses' => ProjectStatus::forOrganization($orgId),
            'people' => User::listAssignable($orgId),
            'prefillClient' => 0,
            'prefillClientModel' => null,
        ]);
    }

    public function update(string $id): void
    {
        $project = $this->editableProject($id);
        $data = $this->formData();
        if (!$this->validateProject($data, 'projects/' . $project->id . '/edit')) {
            return;
        }

        try {
            (new ProjectService())->update($project, $data, $this->actor()->id);
        } catch (\App\Services\WorkflowPending $pending) {
            flash('success', 'Project saved. ' . $pending->getMessage());
            $this->redirect('projects/' . $project->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('projects/' . $project->id . '/edit');
        }

        flash('success', 'Project has been updated.');
        $this->redirect('projects/' . $project->id);
    }

    private function formData(): array
    {
        return Request::only([
            'name', 'client_id', 'code', 'description', 'status', 'priority',
            'start_date', 'due_date', 'budget', 'billing_type', 'manager_user_id',
            'progress', 'payment_status',
        ]);
    }

    private function validateProject(array $data, string $back): bool
    {
        $validator = new Validator($data);
        $ok = $validator->validate([
            'name' => 'required|max:180',
            'client_id' => 'required',
            'code' => 'max:40',
            'status' => 'required|max:80',
            'priority' => 'required|in:low,medium,high,urgent',
            'billing_type' => 'required|in:fixed,hourly,retainer,non_billable',
            'payment_status' => 'required|in:unpaid,partial,paid,not_required',
            'start_date' => 'date',
            'due_date' => 'date',
        ]);

        if ($ok) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);
        return false;
    }

    private function visibleProject(string $id): Project
    {
        $this->authorize('projects.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $project = Project::findInOrganization((int) $id, $this->tenantId());
        if (!$project instanceof Project || !ProjectAccess::canView($project)) {
            Response::notFound();
        }

        return $project;
    }

    private function editableProject(string $id): Project
    {
        $project = $this->visibleProject($id);
        if (!ProjectAccess::canEdit($project)) {
            Response::forbidden();
        }

        return $project;
    }
}
