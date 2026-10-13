<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\FeatureService;
use App\Services\WorkspaceService;

final class WorkspaceController extends Controller
{
    public function create(): void
    {
        $actor = $this->actor();
        if ($actor->organization_id === null) {
            $this->redirect('platform');
        }

        $allowance = FeatureService::workspaceAllowance($actor->email);
        $this->view('workspaces/create', [
            'title' => 'New workspace',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'New workspace'],
            ],
            'allowance' => $allowance,
            'planName' => FeatureService::planNames()[$allowance['plan']] ?? $allowance['plan'],
        ]);
    }

    public function store(): void
    {
        $actor = $this->actor();
        if ($actor->organization_id === null) {
            $this->redirect('platform');
        }

        try {
            $created = (new WorkspaceService())->create(
                $actor,
                (string) Request::input('name', ''),
                trim((string) Request::input('industry', ''))
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('workspaces/create');
        }

        (new WorkspaceService())->open($created);
        $this->clearOld();
        flash('success', 'Workspace created. You are now in ' . trim((string) Request::input('name', '')) . '.');
        $this->redirect('dashboard');
    }
}
