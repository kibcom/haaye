<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Client;
use App\Models\ContentGrid;
use App\Models\ContentGridDay;
use App\Services\ContentGridService;
use App\Services\Phase11Setup;

final class ContentGridController extends Controller
{
    public function index(): void
    {
        $this->authorize('work_flow.view');
        $service = new ContentGridService();
        if (!$service->canView()) {
            Response::forbidden();
        }
        Phase11Setup::applySchema();

        $orgId = $this->tenantId();
        $clientId = (int) Request::input('client', 0);

        $this->view('work-flow/content-grids/index', [
            'title' => 'Monthly Content Grids',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Workflow', 'url' => 'work-flow'],
                ['label' => 'Content Grids'],
            ],
            'grids' => ContentGrid::listFor($orgId, $clientId > 0 ? $clientId : null),
            'clients' => Client::optionsForOrganization($orgId),
            'clientId' => $clientId,
            'canManage' => $service->canManage(),
            'canDelete' => $service->canDelete(),
            'platforms' => ContentGridService::PLATFORMS,
        ]);
    }

    public function show(string $id): void
    {
        $this->authorize('work_flow.view');
        $service = new ContentGridService();
        if (!$service->canView()) {
            Response::forbidden();
        }
        Phase11Setup::applySchema();

        $grid = $this->grid($id);
        $days = ContentGridDay::listForGrid((int) $grid->id);

        $this->view('work-flow/content-grids/show', [
            'title' => 'Monthly Content Grid',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Workflow', 'url' => 'work-flow'],
                ['label' => 'Content Grids', 'url' => 'work-flow/content-grids'],
                ['label' => $grid->client_name ?: 'Grid'],
            ],
            'grid' => $grid,
            'weeks' => ContentGridService::groupByWeek($days),
            'platforms' => ContentGridService::PLATFORMS,
            'postTypes' => ContentGridService::POST_TYPES,
            'canManage' => $service->canManage(),
        ]);
    }

    public function store(): void
    {
        $this->authorize('work_flow.assign');
        if (!(new ContentGridService())->canManage()) {
            Response::forbidden();
        }

        try {
            $grid = (new ContentGridService())->create($this->tenantId(), Request::all(), (int) $this->actor()->id);
            flash('success', 'Monthly content grid created for ' . ($grid->client_name ?: 'the company') . '.');
            $this->redirect('work-flow/content-grids/' . $grid->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('work-flow/content-grids');
        }
    }

    public function update(string $id): void
    {
        $this->authorize('work_flow.assign');
        if (!(new ContentGridService())->canManage()) {
            Response::forbidden();
        }
        $grid = $this->grid($id);

        try {
            (new ContentGridService())->update($grid, Request::all(), (int) $this->actor()->id);
            flash('success', 'Content grid saved.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow/content-grids/' . $grid->id);
    }

    public function destroy(string $id): void
    {
        $this->authorize('work_flow.manage');
        if (!(new ContentGridService())->canDelete()) {
            Response::forbidden();
        }

        $grid = $this->grid($id);
        try {
            (new ContentGridService())->delete($grid, (int) $this->actor()->id);
            flash('success', 'Content grid removed.');
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        $this->redirect('work-flow/content-grids');
    }

    private function grid(string $id): ContentGrid
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        Phase11Setup::applySchema();
        $grid = ContentGrid::findInOrganization((int) $id, $this->tenantId());
        if (!$grid instanceof ContentGrid) {
            Response::notFound();
        }

        return $grid;
    }
}
