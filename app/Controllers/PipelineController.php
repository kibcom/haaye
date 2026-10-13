<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkFlowRole;
use App\Models\WorkflowRun;
use App\Models\WorkflowRunStage;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTemplateStage;
use App\Services\Phase14DemoSetup;
use App\Services\Phase14Setup;
use App\Services\PipelineTemplateService;
use App\Services\PipelineWorkflowEngine;
use App\Services\ProjectAccess;

final class PipelineController extends Controller
{
    public function index(): void
    {
        $this->boot();
        $this->authorize('pipeline.view');
        $orgId = $this->tenantId();

        $this->view('pipeline/index', [
            'title' => 'Pipeline workflows',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Pipeline'],
            ],
            'templates' => WorkflowTemplate::forOrganization($orgId),
            'runs' => WorkflowRun::activeForOrganization($orgId),
            'canManageTemplates' => Auth::can('pipeline.templates'),
            'canManageRuns' => Auth::can('pipeline.manage'),
        ]);
    }

    public function createTemplate(): void
    {
        $this->boot();
        $this->authorize('pipeline.templates');
        $this->templateForm(null);
    }

    public function storeTemplate(): void
    {
        $this->boot();
        $this->authorize('pipeline.templates');
        try {
            $id = (new PipelineTemplateService())->create($this->tenantId(), Request::all(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/templates/create');
        }
        flash('success', 'Workflow template created.');
        $this->redirect('pipeline/templates/' . $id);
    }

    public function showTemplate(string $id): void
    {
        $this->boot();
        $this->authorize('pipeline.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $orgId = $this->tenantId();
        $template = WorkflowTemplate::findInOrganization((int) $id, $orgId);
        if (!$template instanceof WorkflowTemplate) {
            Response::notFound();
        }

        $this->view('pipeline/template', [
            'title' => $template->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Pipeline', 'url' => 'pipeline'],
                ['label' => $template->name],
            ],
            'pipelineTemplate' => $template,
            'stages' => WorkflowTemplateStage::forTemplate((int) $template->id, $orgId, false),
            'roles' => WorkFlowRole::listFor($orgId, true),
            'people' => User::listAssignable($orgId),
            'canManage' => Auth::can('pipeline.templates'),
        ]);
    }

    public function updateTemplate(string $id): void
    {
        $this->boot();
        $this->authorize('pipeline.templates');
        $template = $this->templateOrFail($id);
        try {
            (new PipelineTemplateService())->update($template, Request::all());
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/templates/' . $template->id);
        }
        flash('success', 'Template updated.');
        $this->redirect('pipeline/templates/' . $template->id);
    }

    public function storeStage(string $id): void
    {
        $this->boot();
        $this->authorize('pipeline.templates');
        $template = $this->templateOrFail($id);
        try {
            (new PipelineTemplateService())->addStage($template, Request::all());
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/templates/' . $template->id);
        }
        flash('success', 'Stage added.');
        $this->redirect('pipeline/templates/' . $template->id);
    }

    public function updateStage(string $id, string $stageId): void
    {
        $this->boot();
        $this->authorize('pipeline.templates');
        $template = $this->templateOrFail($id);
        $stage = WorkflowTemplateStage::findInOrganization((int) $stageId, $this->tenantId());
        if (!$stage instanceof WorkflowTemplateStage || (int) $stage->template_id !== (int) $template->id) {
            Response::notFound();
        }
        try {
            (new PipelineTemplateService())->updateStage($stage, Request::all());
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/templates/' . $template->id);
        }
        flash('success', 'Stage updated.');
        $this->redirect('pipeline/templates/' . $template->id);
    }

    public function destroyStage(string $id, string $stageId): void
    {
        $this->boot();
        $this->authorize('pipeline.templates');
        $template = $this->templateOrFail($id);
        $stage = WorkflowTemplateStage::findInOrganization((int) $stageId, $this->tenantId());
        if (!$stage instanceof WorkflowTemplateStage || (int) $stage->template_id !== (int) $template->id) {
            Response::notFound();
        }
        WorkflowTemplateStage::deleteInOrganization((int) $stage->id, $this->tenantId());
        flash('success', 'Stage removed.');
        $this->redirect('pipeline/templates/' . $template->id);
    }

    public function startRun(): void
    {
        $this->boot();
        $this->authorize('pipeline.manage');
        $orgId = $this->tenantId();
        $assigneesRaw = Request::input('assignees', []);
        $assignees = [];
        if (is_array($assigneesRaw)) {
            foreach ($assigneesRaw as $stageId => $userId) {
                if (ctype_digit((string) $stageId) && (int) $userId > 0) {
                    $assignees[(int) $stageId] = (int) $userId;
                }
            }
        }
        try {
            $run = (new PipelineWorkflowEngine())->startRun(
                $orgId,
                (int) Request::input('template_id', 0),
                (int) Request::input('project_id', 0),
                (int) $this->actor()->id,
                trim((string) Request::input('name', '')) ?: null,
                ((int) Request::input('lead_id', 0)) ?: null,
                $assignees,
                (int) $this->actor()->id
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $projectId = (int) Request::input('project_id', 0);
            $this->redirect($projectId > 0 ? 'projects/' . $projectId . '#workflow' : 'pipeline');
        }
        flash('success', 'Pipeline started. First task was assigned — you will approve when they submit.');
        $this->redirect('pipeline/runs/' . $run->id);
    }

    public function showRun(string $id): void
    {
        $this->boot();
        $this->authorize('pipeline.view');
        $run = $this->runOrFail($id);
        $orgId = $this->tenantId();
        $project = Project::findInOrganization((int) $run->project_id, $orgId);
        if ($project instanceof Project && !ProjectAccess::canView($project)) {
            Response::forbidden();
        }

        $this->view('pipeline/run', [
            'title' => $run->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Pipeline', 'url' => 'pipeline'],
                ['label' => $run->name],
            ],
            'run' => $run,
            'stages' => WorkflowRunStage::forRun((int) $run->id, $orgId),
            'people' => User::listAssignable($orgId),
            'canManage' => Auth::can('pipeline.manage') || Auth::can('pipeline.override'),
            'canReassign' => Auth::can('pipeline.manage') || Auth::can('tasks.assign'),
            'pendingApprovals' => $this->pendingApprovalsForRun((int) $run->id, $orgId),
        ]);
    }

    /** @return list<\App\Models\Approval> */
    private function pendingApprovalsForRun(int $runId, int $orgId): array
    {
        $taskIds = [];
        foreach (WorkflowRunStage::forRun($runId, $orgId) as $stage) {
            if ($stage->task_id) {
                $taskIds[] = (int) $stage->task_id;
            }
        }
        if ($taskIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $rows = \App\Core\Database::query(
            'SELECT a.*, ru.name AS requester_name
             FROM approvals a
             LEFT JOIN users ru ON ru.id = a.requested_by
             WHERE a.organization_id = ? AND a.status = ? AND a.entity_type = ?
               AND a.entity_id IN (' . $placeholders . ')
             ORDER BY a.created_at DESC',
            array_merge([$orgId, 'pending', 'task'], $taskIds)
        )->fetchAll();

        return array_map([\App\Models\Approval::class, 'hydrate'], $rows);
    }

    public function confirmNext(string $id): void
    {
        $this->boot();
        $run = $this->runOrFail($id);
        $assignee = (int) Request::input('assignee_user_id', 0);
        try {
            (new PipelineWorkflowEngine())->confirmNext(
                $run,
                (int) $this->actor()->id,
                $assignee > 0 ? $assignee : null
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/runs/' . $run->id);
        }
        flash('success', 'Next stage task created and assigned.');
        $this->redirect('pipeline/runs/' . $run->id);
    }

    public function pause(string $id): void
    {
        $this->boot();
        $run = $this->runOrFail($id);
        try {
            (new PipelineWorkflowEngine())->pause($run, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/runs/' . $run->id);
        }
        flash('success', 'Workflow paused.');
        $this->redirect('pipeline/runs/' . $run->id);
    }

    public function resume(string $id): void
    {
        $this->boot();
        $run = $this->runOrFail($id);
        try {
            (new PipelineWorkflowEngine())->resume($run, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/runs/' . $run->id);
        }
        flash('success', 'Workflow resumed.');
        $this->redirect('pipeline/runs/' . $run->id);
    }

    public function cancel(string $id): void
    {
        $this->boot();
        $run = $this->runOrFail($id);
        try {
            (new PipelineWorkflowEngine())->cancel($run, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/runs/' . $run->id);
        }
        flash('success', 'Workflow cancelled.');
        $this->redirect('pipeline/runs/' . $run->id);
    }

    public function reassignStage(string $id, string $stageId): void
    {
        $this->boot();
        $run = $this->runOrFail($id);
        $stage = WorkflowRunStage::findInOrganization((int) $stageId, $this->tenantId());
        if (!$stage instanceof WorkflowRunStage || (int) $stage->run_id !== (int) $run->id) {
            Response::notFound();
        }
        $assignee = (int) Request::input('assignee_user_id', 0);
        try {
            (new PipelineWorkflowEngine())->reassignStage(
                $stage,
                $assignee > 0 ? $assignee : null,
                (int) $this->actor()->id
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('pipeline/runs/' . $run->id);
        }
        flash('success', 'Stage reassigned.');
        $this->redirect('pipeline/runs/' . $run->id);
    }

    private function boot(): void
    {
        Phase14Setup::applySchema();
        Phase14Setup::seedPermissions();
        Phase14DemoSetup::seed();
    }

    private function templateOrFail(string $id): WorkflowTemplate
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $template = WorkflowTemplate::findInOrganization((int) $id, $this->tenantId());
        if (!$template instanceof WorkflowTemplate) {
            Response::notFound();
        }

        return $template;
    }

    private function runOrFail(string $id): WorkflowRun
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $run = WorkflowRun::findInOrganization((int) $id, $this->tenantId());
        if (!$run instanceof WorkflowRun) {
            Response::notFound();
        }

        return $run;
    }

    private function templateForm(?WorkflowTemplate $template): void
    {
        $this->view('pipeline/template-form', [
            'title' => $template ? 'Edit template' : 'New workflow template',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Pipeline', 'url' => 'pipeline'],
                ['label' => $template ? 'Edit' : 'New template'],
            ],
            'pipelineTemplate' => $template,
        ]);
    }
}
