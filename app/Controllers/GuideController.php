<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Organization;
use App\Services\FeatureService;

final class GuideController extends Controller
{
    public function index(): void
    {
        $slug = Auth::user()?->role()?->slug;
        if (!in_array($slug, ['owner', 'admin'], true)) {
            Response::forbidden();
        }

        $organization = $this->currentOrganization();
        if (!$organization instanceof Organization) {
            Response::notFound();
        }

        $this->view('guide/index', [
            'title' => 'How it works',
            'platform' => false,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Settings', 'url' => 'settings'],
                ['label' => 'How it works'],
            ],
            'guideOrganization' => $organization,
            'organizations' => [],
            'featureOn' => FeatureService::displayed($organization),
        ]);
    }

    public function platform(): void
    {
        $organizations = Database::query(
            'SELECT id, name, company_code FROM organizations ORDER BY name ASC'
        )->fetchAll();
        $requested = (int) Request::input('organization', 0);
        $selectedId = $requested;
        if ($selectedId === 0 && $organizations !== []) {
            $selectedId = (int) $organizations[0]['id'];
        }

        $organization = $selectedId > 0 ? Organization::find($selectedId) : null;
        if ($requested > 0 && !$organization instanceof Organization) {
            Response::notFound();
        }

        $this->view('guide/index', [
            'title' => 'How it works',
            'platform' => true,
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Settings', 'url' => 'settings'],
                ['label' => 'How it works'],
            ],
            'guideOrganization' => $organization,
            'organizations' => $organizations,
            'featureOn' => FeatureService::displayed($organization),
        ]);
    }
}
