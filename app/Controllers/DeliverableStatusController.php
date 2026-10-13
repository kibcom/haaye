<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\DeliverableStatus;

final class DeliverableStatusController extends Controller
{
    public function index(): void
    {
        $this->authorize('deliverables.statuses.manage');
        $orgId = $this->tenantId();
        DeliverableStatus::ensureDefaults($orgId);
        $this->view('deliverables/statuses', [
            'title' => 'Deliverable statuses',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Deliverables', 'url' => 'deliverables'],
                ['label' => 'Statuses'],
            ],
            'statuses' => DeliverableStatus::forOrganization($orgId),
        ]);
    }

    public function store(): void
    {
        $this->authorize('deliverables.statuses.manage');
        $orgId = $this->tenantId();
        $data = Request::only(['name', 'color', 'sort_order', 'is_closed']);
        if (!$this->validateStatus($data)) {
            return;
        }

        $slug = $this->slug((string) $data['name'], $orgId);
        $now = date('Y-m-d H:i:s');
        $id = DeliverableStatus::create([
            'organization_id' => $orgId,
            'name' => $data['name'],
            'slug' => $slug,
            'color' => strtoupper((string) $data['color']),
            'sort_order' => (int) $data['sort_order'],
            'is_closed' => Request::input('is_closed') === '1' ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        ActivityLog::record($orgId, $this->actor()->id, 'deliverable.status_created', 'Deliverable status "' . $data['name'] . '" was added', 'deliverable_status', $id);
        flash('success', 'Deliverable status has been added.');
        $this->redirect('deliverables/statuses');
    }

    public function update(string $id): void
    {
        $this->authorize('deliverables.statuses.manage');
        $status = $this->status($id);
        $data = Request::only(['name', 'color', 'sort_order', 'is_closed']);
        if (!$this->validateStatus($data)) {
            return;
        }

        DeliverableStatus::updateById((int) $status->id, (int) $status->organization_id, [
            'name' => $data['name'],
            'color' => strtoupper((string) $data['color']),
            'sort_order' => (int) $data['sort_order'],
            'is_closed' => Request::input('is_closed') === '1' ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $status->organization_id, $this->actor()->id, 'deliverable.status_updated', 'Deliverable status "' . $data['name'] . '" was updated', 'deliverable_status', (int) $status->id);
        flash('success', 'Deliverable status has been updated.');
        $this->redirect('deliverables/statuses');
    }

    public function destroy(string $id): void
    {
        $this->authorize('deliverables.statuses.manage');
        $status = $this->status($id);
        if (DeliverableStatus::countForOrganization((int) $status->organization_id) <= 1) {
            flash('danger', 'At least one deliverable status is required.');
            $this->redirect('deliverables/statuses');
        }
        if (DeliverableStatus::inUse((int) $status->organization_id, $status->slug)) {
            flash('danger', 'This status is used by a deliverable and cannot be removed.');
            $this->redirect('deliverables/statuses');
        }

        DeliverableStatus::deleteInOrganization((int) $status->id, (int) $status->organization_id);
        flash('success', 'Deliverable status has been removed.');
        $this->redirect('deliverables/statuses');
    }

    private function validateStatus(array $data): bool
    {
        $validator = new Validator($data);
        if ($validator->validate(['name' => 'required|max:80', 'color' => 'required|max:7', 'sort_order' => 'required'])) {
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $data['color']) !== 1 || !is_numeric($data['sort_order'])) {
                flash('danger', 'Check the color and order.');
                $this->redirect('deliverables/statuses');
            }
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect('deliverables/statuses');
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
        while (DeliverableStatus::slugExists($organizationId, $slug, null)) {
            $slug = $base . '_' . $i;
            $i++;
        }

        return $slug;
    }

    private function status(string $id): DeliverableStatus
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $status = DeliverableStatus::findInOrganization((int) $id, $this->tenantId());
        if (!$status instanceof DeliverableStatus) {
            Response::notFound();
        }

        return $status;
    }
}
