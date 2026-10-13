<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Organization;
use App\Models\User;
use App\Services\FeatureService;
use App\Services\OrganizationService;

final class OrganizationController extends Controller
{
    public function index(): void
    {
        $search = (string) Request::input('q', '');
        $status = (string) Request::input('status', '');
        $result = Organization::paginate($this->page(), 15, $search, $status);

        $this->view('platform/organizations/index', [
            'title' => 'Organizations',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Organizations'],
            ],
            'organizations' => $result['items'],
            'pagination' => $result,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        $this->view('platform/organizations/form', [
            'title' => 'New organization',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Organizations', 'url' => 'platform/organizations'],
                ['label' => 'New'],
            ],
            'organization' => null,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
        ]);
    }

    public function store(): void
    {
        $min = (int) Config::get('auth.min_password_length', 10);
        $data = Request::only([
            'name', 'industry', 'email', 'phone', 'address', 'city', 'country',
            'currency', 'timezone', 'tax_id', 'tax_information', 'website',
            'status', 'subscription_plan', 'owner_name', 'owner_email',
            'owner_password', 'owner_password_confirmation',
        ]);

        $validator = new Validator($data);
        if (!$validator->validate([
            'name' => 'required|max:180',
            'email' => 'email|max:190',
            'status' => 'required|in:active,inactive,suspended',
            'subscription_plan' => FeatureService::planRule(),
            'owner_name' => 'required|max:160',
            'owner_email' => 'required|email',
            'owner_password' => 'required|min:' . $min . '|confirmed',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('platform/organizations/create');
        }

        $data['enabled_features'] = FeatureService::encode($this->postedFeatures());
        $data['show_guide'] = Request::input('show_guide', '0') === '1' ? 1 : 0;

        try {
            $organization = (new OrganizationService())->create(
                $data,
                Request::file('logo'),
                [
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => $data['owner_password'],
                ],
                $this->actor()->id
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('platform/organizations/create');
        }

        flash('success', 'Organization created.');
        $this->redirect('platform/organizations/' . $organization->id);
    }

    public function show(string $id): void
    {
        $organization = $this->findOrg($id);
        $people = User::paginateForOrganization((int) $organization->id, 1, 20);

        $this->view('platform/organizations/show', [
            'title' => $organization->name,
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Organizations', 'url' => 'platform/organizations'],
                ['label' => $organization->name],
            ],
            'organization' => $organization,
            'users' => $people['items'],
        ]);
    }

    public function edit(string $id): void
    {
        $organization = $this->findOrg($id);

        $this->view('platform/organizations/form', [
            'title' => 'Edit organization',
            'breadcrumbs' => [
                ['label' => 'Platform', 'url' => 'platform'],
                ['label' => 'Organizations', 'url' => 'platform/organizations'],
                ['label' => 'Edit'],
            ],
            'organization' => $organization,
            'minPassword' => (int) Config::get('auth.min_password_length', 10),
        ]);
    }

    public function update(string $id): void
    {
        $organization = $this->findOrg($id);
        $data = Request::only([
            'name', 'industry', 'email', 'phone', 'address', 'city', 'country',
            'currency', 'timezone', 'tax_id', 'tax_information', 'website',
            'status', 'subscription_plan',
        ]);

        $validator = new Validator($data);
        if (!$validator->validate([
            'name' => 'required|max:180',
            'email' => 'email|max:190',
            'status' => 'required|in:active,inactive,suspended',
            'subscription_plan' => FeatureService::planRule(),
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('platform/organizations/' . $organization->id . '/edit');
        }

        $data['enabled_features'] = FeatureService::encode($this->postedFeatures());
        $data['show_guide'] = Request::input('show_guide', '0') === '1' ? 1 : 0;

        try {
            (new OrganizationService())->update($organization, $data, Request::file('logo'), $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('platform/organizations/' . $organization->id . '/edit');
        }

        flash('success', 'Organization updated.');
        $this->redirect('platform/organizations/' . $organization->id);
    }

    /** @return list<string> */
    private function postedFeatures(): array
    {
        return FeatureService::sanitize(Request::input('features', []));
    }

    private function findOrg(string $id): Organization
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $organization = Organization::find((int) $id);
        if (!$organization instanceof Organization) {
            Response::notFound();
        }

        return $organization;
    }
}
