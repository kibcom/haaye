<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Validator;
use App\Services\BillingService;
use App\Services\OrganizationService;
use App\Services\PlanUpgradeService;

final class OrganizationController extends Controller
{
    public function settings(): void
    {
        $this->authorize('organization.view');
        $organization = $this->currentOrganization();
        if ($organization === null) {
            $this->redirect('dashboard');
        }

        $this->view('organizations/settings', [
            'title' => 'Organization settings',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Organization'],
            ],
            'organization' => $organization,
            'canEdit' => can('organization.update'),
            'billing' => (new BillingService())->present($organization),
            'upgrade' => PlanUpgradeService::pending((int) $organization->id),
        ]);
    }

    public function plan(): void
    {
        $this->authorize('organization.update');
        $organization = $this->currentOrganization();
        if ($organization === null) {
            $this->redirect('dashboard');
        }

        $names = \App\Services\FeatureService::planNames();
        $this->view('organizations/plan', [
            'title' => 'Update plan',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Organization', 'url' => 'settings/organization'],
                ['label' => 'Update plan'],
            ],
            'organization' => $organization,
            'planName' => (string) ($names[$organization->subscription_plan] ?? $organization->subscription_plan),
            'currentPlan' => PlanUpgradeService::describe($organization->subscription_plan),
            'choices' => PlanUpgradeService::choices($organization),
            'pending' => PlanUpgradeService::pending((int) $organization->id),
            'latest' => PlanUpgradeService::latest((int) $organization->id),
        ]);
    }

    public function submitPlan(): void
    {
        $this->authorize('organization.update');
        $organization = $this->currentOrganization();
        if ($organization === null) {
            $this->redirect('dashboard');
        }

        $data = Request::only(['plan', 'method', 'reference', 'note']);
        $codes = array_column(PlanUpgradeService::choices($organization), 'code');
        $validator = new Validator($data);
        if (!$validator->validate([
            'plan' => 'required|in:' . implode(',', $codes),
            'method' => 'required|in:' . implode(',', PlanUpgradeService::METHODS),
            'reference' => 'max:80',
            'note' => 'max:500',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('settings/organization/plan');
        }

        try {
            PlanUpgradeService::submit(
                $organization,
                $this->actor(),
                (string) $data['plan'],
                (string) $data['method'],
                trim((string) ($data['reference'] ?? '')),
                trim((string) ($data['note'] ?? '')),
                Request::file('screenshot')
            );
        } catch (\RuntimeException $exception) {
            flash('danger', $exception->getMessage());
            $this->withErrors([], $data);
            $this->redirect('settings/organization/plan');
        }

        flash('success', 'Your plan request was sent. The platform owner will check the payment and screenshot.');
        $this->redirect('settings/organization/plan');
    }

    public function updateSettings(): void
    {
        $this->authorize('organization.update');
        $organization = $this->currentOrganization();
        if ($organization === null) {
            $this->redirect('dashboard');
        }

        $data = Request::only([
            'name', 'industry', 'email', 'phone', 'address', 'city', 'country',
            'currency', 'timezone', 'tax_id', 'tax_information', 'website',
            'invoice_prefix', 'invoice_next_number', 'show_time',
        ]);

        $validator = new Validator($data);
        if (!$validator->validate([
            'name' => 'required|max:180',
            'email' => 'email|max:190',
            'currency' => 'required|max:3',
            'timezone' => 'required|max:80',
            'invoice_prefix' => 'required|max:12',
            'invoice_next_number' => 'required|max:6',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('settings/organization');
        }

        try {
            $removeLogo = (string) Request::input('remove_logo', '0') === '1';
            (new OrganizationService())->update($organization, $data, Request::file('logo'), $this->actor()->id, $removeLogo);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('settings/organization');
        }

        flash('success', 'Organization settings have been saved.');
        $this->redirect('settings/organization');
    }
}
