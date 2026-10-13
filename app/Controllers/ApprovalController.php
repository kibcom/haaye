<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Approval;
use App\Services\ApprovalService;

final class ApprovalController extends Controller
{
    public function index(): void
    {
        $this->authorize('approvals.view');
        $status = (string) Request::input('status', 'pending');
        if (!in_array($status, ['', 'pending', 'approved', 'rejected', 'changes_requested', 'cancelled'], true)) {
            $status = 'pending';
        }

        $result = Approval::paginate(
            $this->tenantId(),
            $this->page(),
            20,
            $status,
            null,
            ApprovalService::viewerClientId(),
            ApprovalService::projectRestriction()
        );

        $items = $result['items'];
        $canDecideMap = [];
        foreach ($items as $item) {
            $canDecideMap[(int) $item->id] = ApprovalService::canDecide($item);
        }

        $this->view('approvals/index', [
            'title' => 'Approvals',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Approvals'],
            ],
            'approvals' => $items,
            'pagination' => $result,
            'status' => $status,
            'canDecideMap' => $canDecideMap,
        ]);
    }

    public function show(string $id): void
    {
        $approval = $this->visible($id);
        $this->view('approvals/show', [
            'title' => 'Approval',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Approvals', 'url' => 'approvals'],
                ['label' => $approval->entity_label],
            ],
            'approval' => $approval,
            'canDecide' => ApprovalService::canDecide($approval),
        ]);
    }

    public function decide(string $id): void
    {
        $approval = $this->visible($id);
        try {
            (new ApprovalService())->decide(
                $approval,
                (string) Request::input('decision', ''),
                (string) Request::input('comment', ''),
                $this->actor()->id
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('approvals/' . $approval->id);
        }

        $decision = (string) Request::input('decision', '');
        flash('success', $decision === 'approved' ? 'Approved. Next pipeline task will start if assigned.' : 'The decision has been recorded.');
        $back = trim((string) Request::input('return_to', ''));
        if ($back !== '' && !str_contains($back, '://')) {
            $this->redirect(ltrim($back, '/'));
        }
        $this->redirect('approvals?status=pending');
    }

    private function visible(string $id): Approval
    {
        $this->authorize('approvals.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $approval = Approval::findInOrganization((int) $id, $this->tenantId());
        if (!$approval instanceof Approval || !ApprovalService::canSee($approval)) {
            Response::notFound();
        }

        return $approval;
    }
}
