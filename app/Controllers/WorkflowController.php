<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\DeliverableStatus;
use App\Models\ProjectStatus;
use App\Models\TaskStatus;
use App\Models\WorkflowTransition;
use App\Services\StatusWorkflowService;

final class WorkflowController extends Controller
{
    public function index(): void
    {
        $this->authorize('workflows.view');
        $orgId = $this->tenantId();
        TaskStatus::ensureDefaults($orgId);
        ProjectStatus::ensureDefaults($orgId);
        DeliverableStatus::ensureDefaults($orgId);

        $type = (string) Request::input('type', 'task');
        if (!isset(StatusWorkflowService::TYPES[$type])) {
            $type = 'task';
        }

        $transitions = WorkflowTransition::forOrganization($orgId, $type);
        $this->view('workflows/index', [
            'title' => 'Workflows',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Workflows'],
            ],
            'types' => StatusWorkflowService::TYPES,
            'type' => $type,
            'transitions' => $transitions,
            'restricted' => $transitions !== [],
            'statuses' => (new StatusWorkflowService())->statusesForType($orgId, $type),
            'canManage' => Auth::can('workflows.manage'),
        ]);
    }

    public function store(): void
    {
        $this->authorize('workflows.manage');
        $orgId = $this->tenantId();
        $type = (string) Request::input('entity_type', 'task');
        if (!isset(StatusWorkflowService::TYPES[$type])) {
            flash('danger', 'Choose a valid workflow type.');
            $this->redirect('workflows');
        }

        $from = trim((string) Request::input('from_status', ''));
        $to = trim((string) Request::input('to_status', ''));
        $approval = (string) Request::input('requires_approval', 'none');
        if ($from === '' || $to === '' || $from === $to) {
            flash('danger', 'Choose different from and to statuses.');
            $this->redirect('workflows?type=' . $type);
        }
        if (!in_array($approval, ['none', 'internal', 'client'], true)) {
            $approval = 'none';
        }
        if (WorkflowTransition::exists($orgId, $type, $from, $to)) {
            flash('danger', 'That transition already exists.');
            $this->redirect('workflows?type=' . $type);
        }

        WorkflowTransition::create([
            'organization_id' => $orgId,
            'entity_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'requires_approval' => $approval,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        flash('success', 'Transition added.');
        $this->redirect('workflows?type=' . $type);
    }

    public function destroy(string $id): void
    {
        $this->authorize('workflows.manage');
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $transition = WorkflowTransition::findInOrganization((int) $id, $this->tenantId());
        if (!$transition instanceof WorkflowTransition) {
            Response::notFound();
        }
        $type = $transition->entity_type;
        WorkflowTransition::deleteInOrganization((int) $transition->id, $this->tenantId());
        flash('success', 'Transition removed.');
        $this->redirect('workflows?type=' . $type);
    }
}
