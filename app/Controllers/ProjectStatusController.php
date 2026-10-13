<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\ProjectStatus;

final class ProjectStatusController extends Controller
{
    public function index(): void
    {
        $this->authorize('projects.statuses.manage');
        $orgId = $this->tenantId();
        ProjectStatus::ensureDefaults($orgId);

        $this->view('projects/statuses', [
            'title' => 'Project statuses',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects', 'url' => 'projects'],
                ['label' => 'Statuses'],
            ],
            'statuses' => ProjectStatus::forOrganization($orgId),
        ]);
    }

    public function store(): void
    {
        $this->authorize('projects.statuses.manage');
        $orgId = $this->tenantId();
        $data = Request::only(['name', 'color', 'sort_order', 'is_closed']);
        if (!$this->validateStatus($data, 'projects/statuses')) {
            return;
        }

        $slug = $this->slug((string) $data['name'], $orgId, null);
        $now = date('Y-m-d H:i:s');
        $id = ProjectStatus::create([
            'organization_id' => $orgId,
            'name' => $data['name'],
            'slug' => $slug,
            'color' => strtoupper((string) $data['color']),
            'sort_order' => (int) $data['sort_order'],
            'is_closed' => Request::input('is_closed') === '1' ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($orgId, $this->actor()->id, 'project.status_created', 'Project status "' . $data['name'] . '" was added', 'project_status', $id);
        flash('success', 'Project status has been added.');
        $this->redirect('projects/statuses');
    }

    public function update(string $id): void
    {
        $this->authorize('projects.statuses.manage');
        $status = $this->status($id);
        $data = Request::only(['name', 'color', 'sort_order', 'is_closed']);
        $back = 'projects/statuses';

        if (!$this->validateStatus($data, $back)) {
            return;
        }

        ProjectStatus::updateById((int) $status->id, (int) $status->organization_id, [
            'name' => $data['name'],
            'color' => strtoupper((string) $data['color']),
            'sort_order' => (int) $data['sort_order'],
            'is_closed' => Request::input('is_closed') === '1' ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record((int) $status->organization_id, $this->actor()->id, 'project.status_updated', 'Project status "' . $data['name'] . '" was updated', 'project_status', (int) $status->id);
        flash('success', 'Project status has been updated.');
        $this->redirect('projects/statuses');
    }

    public function destroy(string $id): void
    {
        $this->authorize('projects.statuses.manage');
        $status = $this->status($id);

        if (ProjectStatus::countForOrganization((int) $status->organization_id) <= 1) {
            flash('danger', 'At least one project status is required.');
            $this->redirect('projects/statuses');
        }

        if (ProjectStatus::inUse((int) $status->organization_id, $status->slug)) {
            flash('danger', 'This status is used by a project and cannot be removed.');
            $this->redirect('projects/statuses');
        }

        ProjectStatus::deleteInOrganization((int) $status->id, (int) $status->organization_id);
        ActivityLog::record((int) $status->organization_id, $this->actor()->id, 'project.status_deleted', 'Project status "' . $status->name . '" was removed', 'project_status', (int) $status->id);
        flash('success', 'Project status has been removed.');
        $this->redirect('projects/statuses');
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

    private function slug(string $name, int $organizationId, ?int $exceptId): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));
        if ($base === '') {
            $base = 'status';
        }

        $slug = $base;
        $i = 2;
        while (ProjectStatus::slugExists($organizationId, $slug, $exceptId)) {
            $slug = $base . '_' . $i;
            $i++;
        }

        return $slug;
    }

    private function status(string $id): ProjectStatus
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $status = ProjectStatus::findInOrganization((int) $id, $this->tenantId());
        if (!$status instanceof ProjectStatus) {
            Response::notFound();
        }

        return $status;
    }
}
