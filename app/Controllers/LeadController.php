<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Lead;
use App\Models\User;
use App\Services\CrmAccess;
use App\Services\LeadService;

final class LeadController extends Controller
{
    public function index(): void
    {
        $this->authorize('leads.view');
        $orgId = $this->tenantId();
        $status = (string) Request::input('status', '');
        if (!in_array($status, LeadService::STATUSES, true)) {
            $status = '';
        }
        $search = trim((string) Request::input('q', ''));
        $ownerScope = CrmAccess::restrictLeadOwnerId();

        $this->view('leads/index', [
            'title' => 'Leads',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Leads'],
            ],
            'leads' => Lead::listFor(
                $orgId,
                $status !== '' ? $status : null,
                $search !== '' ? $search : null,
                $ownerScope
            ),
            'counts' => Lead::counts($orgId, $ownerScope),
            'status' => $status,
            'search' => $search,
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
            'scopedToSelf' => $ownerScope !== null,
        ]);
    }

    public function create(): void
    {
        $this->authorize('leads.create');
        $this->form(null);
    }

    public function store(): void
    {
        $this->authorize('leads.create');
        $data = $this->formData();
        if (!$this->validateLead($data, 'leads/create')) {
            return;
        }

        try {
            $lead = (new LeadService())->create($this->tenantId(), $data, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('leads/create');
        }

        $this->clearOld();
        flash('success', 'Lead has been added.');
        $this->redirect('leads/' . $lead->id);
    }

    public function show(string $id): void
    {
        $lead = $this->visible($id);
        $this->view('leads/show', [
            'title' => $lead->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Leads', 'url' => 'leads'],
                ['label' => $lead->name],
            ],
            'lead' => $lead,
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
            'canEdit' => Auth::can('leads.update') && $lead->status !== 'converted',
            'canConvert' => Auth::can('leads.convert') && Auth::can('clients.create') && $lead->client_id === null,
            'canOpenProject' => Auth::can('projects.create'),
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorize('leads.update');
        $lead = $this->visible($id);
        if ($lead->status === 'converted') {
            flash('danger', 'A converted lead is kept as history.');
            $this->redirect('leads/' . $lead->id);
        }
        $this->form($lead);
    }

    public function stage(string $id): void
    {
        $this->authorize('leads.update');
        $lead = $this->visible($id);
        $status = (string) Request::input('status', '');
        try {
            (new LeadService())->setStage($lead, $status, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('leads/' . $lead->id);
        }

        flash('success', 'The stage has been updated.');
        $this->redirect('leads/' . $lead->id);
    }

    public function update(string $id): void
    {
        $this->authorize('leads.update');
        $lead = $this->visible($id);
        $data = $this->formData();
        if (!$this->validateLead($data, 'leads/' . $lead->id . '/edit')) {
            return;
        }

        try {
            (new LeadService())->update($lead, $data, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('leads/' . $lead->id . '/edit');
        }

        $this->clearOld();
        flash('success', 'Lead has been updated.');
        $this->redirect('leads/' . $lead->id);
    }

    public function convert(string $id): void
    {
        $this->authorize('leads.convert');
        if (!Auth::can('clients.create')) {
            flash('danger', 'You need permission to add a client before converting a lead.');
            $this->redirect('leads/' . $id);
        }
        $lead = $this->visible($id);
        try {
            $lead = (new LeadService())->convert($lead, [
                'open_project' => Auth::can('projects.create') ? (string) Request::input('open_project', '') : '',
                'project_name' => (string) Request::input('project_name', ''),
            ], (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('leads/' . $lead->id);
        }

        flash('success', $lead->project_id ? 'Lead is now a client and a project.' : 'Lead is now a client.');
        $this->redirect($lead->project_id ? 'projects/' . $lead->project_id : 'clients/' . $lead->client_id);
    }

    public function destroy(string $id): void
    {
        $this->authorize('leads.update');
        $lead = $this->visible($id);
        (new LeadService())->delete($lead, (int) $this->actor()->id);
        flash('success', 'Lead has been removed.');
        $this->redirect('leads');
    }

    private function form(?Lead $lead): void
    {
        $this->view('leads/form', [
            'title' => $lead instanceof Lead ? 'Edit lead' : 'New lead',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Leads', 'url' => 'leads'],
                ['label' => $lead instanceof Lead ? 'Edit' : 'New lead'],
            ],
            'lead' => $lead,
            'owners' => User::listAssignable($this->tenantId()),
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
        ]);
    }

    private function formData(): array
    {
        return Request::only([
            'name', 'company', 'email', 'phone', 'source', 'status', 'estimated_value', 'notes', 'owner_user_id',
        ]);
    }

    private function validateLead(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'name' => 'required|max:160',
            'company' => 'max:180',
            'email' => 'email|max:190',
            'phone' => 'max:50',
            'source' => 'required|in:' . implode(',', LeadService::SOURCES),
            'status' => 'required|in:' . implode(',', LeadService::STATUSES),
            'notes' => 'max:4000',
        ])) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);

        return false;
    }

    private function visible(string $id): Lead
    {
        $this->authorize('leads.view');
        if (!ctype_digit($id)) {
            Response::notFound();
        }
        $lead = Lead::findInOrganization((int) $id, $this->tenantId());
        if (!$lead instanceof Lead || !CrmAccess::canViewLead($lead)) {
            Response::notFound();
        }

        return $lead;
    }
}
