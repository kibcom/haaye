<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Services\PlanUpgradeService;

final class PlanRequestController extends Controller
{
    public function index(): void
    {
        $this->authorize('platform.plans.view');
        $this->view('platform/plan-requests/index', [
            'title' => 'Plan requests',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Plan requests'],
            ],
            'requests' => PlanUpgradeService::all(),
        ]);
    }

    public function show(string $id): void
    {
        $this->authorize('platform.plans.view');
        $request = PlanUpgradeService::find((int) $id);
        if ($request === null) {
            flash('danger', 'That plan request could not be found.');
            $this->redirect('platform/plan-requests');
        }

        $this->view('platform/plan-requests/show', [
            'title' => 'Plan request',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Plan requests', 'url' => 'platform/plan-requests'],
                ['label' => (string) $request['organization_name']],
            ],
            'request' => $request,
            'canReview' => can('platform.plans.update') && (string) $request['status'] === 'pending',
        ]);
    }

    public function approve(string $id): void
    {
        $this->review((int) $id, true);
    }

    public function reject(string $id): void
    {
        $this->review((int) $id, false);
    }

    private function review(int $id, bool $approve): void
    {
        $this->authorize('platform.plans.update');
        $note = trim((string) Request::input('review_note', ''));
        $validator = new Validator(['review_note' => $note]);
        $rules = $approve ? ['review_note' => 'max:500'] : ['review_note' => 'required|max:500'];
        if (!$validator->validate($rules)) {
            $this->withErrors($validator->errors());
            $this->redirect('platform/plan-requests/' . $id);
        }

        try {
            if ($approve) {
                PlanUpgradeService::approve($id, $this->actor(), $note);
            } else {
                PlanUpgradeService::reject($id, $this->actor(), $note);
            }
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            $this->redirect('platform/plan-requests/' . $id);
        }

        flash('success', $approve ? 'The plan upgrade was approved.' : 'The plan request was rejected.');
        $this->redirect('platform/plan-requests/' . $id);
    }
}
