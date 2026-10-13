<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\TaskStatus;

final class TaskStatusController extends Controller
{
    public function index(): void
    {
        $this->authorize('tasks.statuses.manage');
        $orgId = $this->tenantId();
        TaskStatus::ensureDefaults($orgId);

        $this->view('tasks/statuses', [
            'title' => 'Task statuses',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Tasks', 'url' => 'tasks'],
                ['label' => 'Statuses'],
            ],
            'statuses' => TaskStatus::forOrganization($orgId),
        ]);
    }

    public function store(): void
    {
        $this->authorize('tasks.statuses.manage');
        $orgId = $this->tenantId();
        $data = Request::only(['name', 'color', 'sort_order', 'is_closed']);
        if (!$this->validateStatus($data, 'tasks/statuses')) {
            return;
        }

        $slug = $this->slug((string) $data['name'], $orgId);
        $now = date('Y-m-d H:i:s');
        $id = TaskStatus::create([
            'organization_id' => $orgId,
            'name' => $data['name'],
            'slug' => $slug,
            'color' => strtoupper((string) $data['color']),
            'sort_order' => (int) $data['sort_order'],
            'is_closed' => Request::input('is_closed') === '1' ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($orgId, $this->actor()->id, 'task.status_created', 'Task status "' . $data['name'] . '" was added', 'task_status', $id);
        flash('success', 'Task status has been added.');
        $this->redirect('tasks/statuses');
    }

    public function update(string $id): void
    {
        $this->authorize('tasks.statuses.manage');
        $status = $this->status($id);
        $data = Request::only(['name', 'color', 'sort_order', 'is_closed']);
        if (!$this->validateStatus($data, 'tasks/statuses')) {
            return;
        }

        TaskStatus::updateById((int) $status->id, (int) $status->organization_id, [
            'name' => $data['name'],
            'color' => strtoupper((string) $data['color']),
            'sort_order' => (int) $data['sort_order'],
            'is_closed' => Request::input('is_closed') === '1' ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Project::syncOrganization((int) $status->organization_id);
        ActivityLog::record((int) $status->organization_id, $this->actor()->id, 'task.status_updated', 'Task status "' . $data['name'] . '" was updated', 'task_status', (int) $status->id);
        flash('success', 'Task status has been updated.');
        $this->redirect('tasks/statuses');
    }

    public function destroy(string $id): void
    {
        $this->authorize('tasks.statuses.manage');
        $status = $this->status($id);

        if (TaskStatus::countForOrganization((int) $status->organization_id) <= 1) {
            flash('danger', 'At least one task status is required.');
            $this->redirect('tasks/statuses');
        }

        if (TaskStatus::inUse((int) $status->organization_id, $status->slug)) {
            flash('danger', 'This status is used by a task and cannot be removed.');
            $this->redirect('tasks/statuses');
        }

        TaskStatus::deleteInOrganization((int) $status->id, (int) $status->organization_id);
        ActivityLog::record((int) $status->organization_id, $this->actor()->id, 'task.status_deleted', 'Task status "' . $status->name . '" was removed', 'task_status', (int) $status->id);
        flash('success', 'Task status has been removed.');
        $this->redirect('tasks/statuses');
    }

    private function validateStatus(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'name' => 'required|max:80',
            'color' => 'required|max:7',
            'sort_order' => 'required',
        ])) {
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $data['color']) !== 1) {
                $this->withErrors(['color' => ['Color must be a hex value like #00AFC6.']]);
                $this->redirect($back);
                return false;
            }

            if (!is_numeric($data['sort_order'])) {
                $this->withErrors(['sort_order' => ['Order must be a number.']]);
                $this->redirect($back);
                return false;
            }

            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);
        return false;
    }

    private function slug(string $name, int $organizationId): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));
        if ($base === '') {
            $base = 'status';
        }

        $slug = $base;
        $i = 2;
        while (TaskStatus::slugExists($organizationId, $slug, null)) {
            $slug = $base . '_' . $i;
            $i++;
        }

        return $slug;
    }

    private function status(string $id): TaskStatus
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $status = TaskStatus::findInOrganization((int) $id, $this->tenantId());
        if (!$status instanceof TaskStatus) {
            Response::notFound();
        }

        return $status;
    }
}
