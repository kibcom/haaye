<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\FileUploader;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Deliverable;
use App\Models\DeliverableStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\DeliverableAccess;
use App\Services\DeliverableService;
use App\Services\WorkflowPending;

final class DeliverableController extends Controller
{
    public function index(): void
    {
        $this->authorize('deliverables.view');
        $orgId = $this->tenantId();
        DeliverableStatus::ensureDefaults($orgId);
        $search = (string) Request::input('q', '');
        $status = (string) Request::input('status', '');
        $projectId = (int) Request::input('project_id', 0);
        $result = Deliverable::paginate(
            $orgId,
            $this->page(),
            20,
            $search,
            $status,
            $projectId > 0 ? $projectId : null,
            DeliverableAccess::restrictToUserId(),
            DeliverableAccess::clientScope(),
            DeliverableAccess::visibleStatuses()
        );

        $this->view('deliverables/index', [
            'title' => 'Deliverables',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Deliverables'],
            ],
            'deliverables' => $result['items'],
            'pagination' => $result,
            'search' => $search,
            'status' => $status,
            'projectId' => $projectId,
            'projects' => DeliverableAccess::clientScope() === null
                ? Project::optionsVisible($orgId, DeliverableAccess::restrictToUserId())
                : [],
            'statuses' => DeliverableStatus::forOrganization($orgId),
        ]);
    }

    public function create(): void
    {
        $this->authorize('deliverables.create');
        $orgId = $this->tenantId();
        DeliverableStatus::ensureDefaults($orgId);
        $projectId = (int) Request::input('project', 0);
        $this->view('deliverables/form', $this->formView($orgId, null, $projectId));
    }

    public function store(): void
    {
        $this->authorize('deliverables.create');
        $data = $this->formData();
        if (!$this->validateDeliverable($data, 'deliverables/create')) {
            return;
        }

        try {
            $deliverable = (new DeliverableService())->create($this->tenantId(), $data, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('deliverables/create');
        }

        $this->clearOld();
        flash('success', 'Deliverable has been created.');
        $this->redirect('deliverables/' . $deliverable->id);
    }

    public function show(string $id): void
    {
        $deliverable = $this->visible($id);
        $orgId = (int) $deliverable->organization_id;
        $this->view('deliverables/show', [
            'title' => $deliverable->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Deliverables', 'url' => 'deliverables'],
                ['label' => $deliverable->name],
            ],
            'deliverable' => $deliverable,
            'statuses' => DeliverableStatus::forOrganization($orgId),
            'approvals' => Approval::historyForEntity($orgId, 'deliverable', (int) $deliverable->id),
            'activity' => ActivityLog::recentForEntity($orgId, 'deliverable', (int) $deliverable->id),
            'canUpdate' => DeliverableAccess::canUpdate($deliverable),
        ]);
    }

    public function edit(string $id): void
    {
        $deliverable = $this->editable($id);
        $this->view('deliverables/form', $this->formView((int) $deliverable->organization_id, $deliverable, 0));
    }

    public function update(string $id): void
    {
        $deliverable = $this->editable($id);
        $data = $this->formData();
        if (!$this->validateDeliverable($data, 'deliverables/' . $deliverable->id . '/edit')) {
            return;
        }

        try {
            (new DeliverableService())->update($deliverable, $data, $this->actor()->id);
        } catch (WorkflowPending $pending) {
            flash('success', 'Deliverable saved. ' . $pending->getMessage());
            $this->redirect('deliverables/' . $deliverable->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('deliverables/' . $deliverable->id . '/edit');
        }

        $this->clearOld();
        flash('success', 'Deliverable has been updated.');
        $this->redirect('deliverables/' . $deliverable->id);
    }

    public function move(string $id): void
    {
        $deliverable = $this->editable($id);
        try {
            (new DeliverableService())->changeStatus($deliverable, (string) Request::input('status', ''), $this->actor()->id);
        } catch (WorkflowPending $pending) {
            flash('success', $pending->getMessage());
            $this->redirect('deliverables/' . $deliverable->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('deliverables/' . $deliverable->id);
        }

        flash('success', 'Deliverable status has been updated.');
        $this->redirect('deliverables/' . $deliverable->id);
    }

    public function upload(string $id): void
    {
        $deliverable = $this->editable($id);
        $file = Request::file('file');
        if ($file === null) {
            flash('danger', 'Choose a file to upload.');
            $this->redirect('deliverables/' . $deliverable->id);
        }

        try {
            (new DeliverableService())->storeFile($deliverable, $file, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('deliverables/' . $deliverable->id);
        }

        flash('success', 'A new file version has been saved.');
        $this->redirect('deliverables/' . $deliverable->id);
    }

    public function download(string $id): void
    {
        $deliverable = $this->visible($id);
        if ($deliverable->stored_path === null || $deliverable->stored_path === '') {
            Response::notFound();
        }
        $path = FileUploader::privateAbsolutePath($deliverable->stored_path);
        if ($path === null || !is_file($path)) {
            Response::notFound();
        }

        $name = str_replace(['"', "\r", "\n"], '', (string) $deliverable->original_name);
        if ($name === '') {
            $name = 'deliverable';
        }
        header('Content-Type: ' . ($deliverable->mime_type ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function destroy(string $id): void
    {
        $deliverable = $this->editable($id);
        try {
            (new DeliverableService())->delete($deliverable, $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('deliverables/' . $deliverable->id);
        }

        flash('success', 'Deliverable has been removed.');
        $this->redirect('deliverables');
    }

    private function formView(int $orgId, ?Deliverable $deliverable, int $prefill): array
    {
        $projects = Project::optionsVisible($orgId, DeliverableAccess::restrictToUserId());
        $projectId = (int) ($deliverable->project_id ?? $prefill);
        $tasks = [];
        if ($projectId > 0) {
            $tasks = Task::forProject($projectId, $orgId, 100);
        }

        return [
            'title' => $deliverable ? 'Edit deliverable' : 'New deliverable',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Deliverables', 'url' => 'deliverables'],
                ['label' => $deliverable ? 'Edit' : 'New deliverable'],
            ],
            'deliverable' => $deliverable,
            'projects' => $projects,
            'tasks' => $tasks,
            'statuses' => DeliverableStatus::forOrganization($orgId),
            'people' => User::listAssignable($orgId),
            'prefillProject' => $prefill,
        ];
    }

    private function formData(): array
    {
        return Request::only(['name', 'description', 'project_id', 'task_id', 'assignee_user_id', 'status', 'due_date']);
    }

    private function validateDeliverable(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'name' => 'required|max:180',
            'description' => 'max:5000',
            'project_id' => 'required',
            'status' => 'required|max:80',
            'due_date' => 'date',
        ])) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);
        return false;
    }

    private function visible(string $id): Deliverable
    {
        $this->authorize('deliverables.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $deliverable = Deliverable::findInOrganization((int) $id, $this->tenantId());
        if (!$deliverable instanceof Deliverable || !DeliverableAccess::canView($deliverable)) {
            Response::notFound();
        }

        return $deliverable;
    }

    private function editable(string $id): Deliverable
    {
        $deliverable = $this->visible($id);
        if (!DeliverableAccess::canUpdate($deliverable)) {
            Response::forbidden();
        }

        return $deliverable;
    }
}
