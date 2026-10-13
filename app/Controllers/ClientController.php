<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientService;
use App\Services\CrmAccess;

final class ClientController extends Controller
{
    public function index(): void
    {
        $this->authorize('clients.view');
        $orgId = $this->tenantId();
        $search = (string) Request::input('q', '');
        $status = (string) Request::input('status', '');
        $assigned = (int) Request::input('assigned', 0);
        $scopeSelf = CrmAccess::restrictClientAssigneeId();
        if ($scopeSelf !== null) {
            $assigned = $scopeSelf;
        }

        $result = Client::paginateForOrganization(
            $orgId,
            $this->page(),
            15,
            $search,
            $status,
            $assigned > 0 ? $assigned : null
        );

        $this->view('clients/index', [
            'title' => 'Clients',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Clients'],
            ],
            'clients' => $result['items'],
            'pagination' => $result,
            'search' => $search,
            'status' => $status,
            'assigned' => $assigned,
            'assignees' => CrmAccess::seesAllClients() ? User::listAssignable($orgId) : [],
            'scopedToSelf' => $scopeSelf !== null,
            'canFilterOwners' => CrmAccess::seesAllClients(),
        ]);
    }

    public function create(): void
    {
        $this->authorize('clients.create');
        $this->view('clients/form', [
            'title' => 'Add client',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Clients', 'url' => 'clients'],
                ['label' => 'Add client'],
            ],
            'client' => null,
            'assignees' => User::listAssignable($this->tenantId()),
        ]);
    }

    public function store(): void
    {
        $this->authorize('clients.create');
        $data = $this->formData();

        if (!$this->validateClient($data, 'clients/create')) {
            return;
        }

        $client = (new ClientService())->create($this->tenantId(), $data, $this->actor()->id);
        flash('success', 'Client has been added.');
        $this->redirect('clients/' . $client->id);
    }

    public function show(string $id): void
    {
        $this->authorize('clients.view');
        $client = $this->tenantClient($id);
        $orgId = $this->tenantId();

        $this->view('clients/show', [
            'title' => $client->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Clients', 'url' => 'clients'],
                ['label' => $client->name],
            ],
            'client' => $client,
            'contacts' => ClientContact::forClient((int) $client->id, $orgId),
            'projects' => Project::forClient((int) $client->id, $orgId),
            'activity' => ActivityLog::recentForEntity($orgId, 'client', (int) $client->id),
            'contactCount' => ClientContact::countForClient((int) $client->id, $orgId),
            'projectCount' => Project::countForClient((int) $client->id, $orgId),
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorize('clients.update');
        $client = $this->tenantClient($id);

        $this->view('clients/form', [
            'title' => 'Edit ' . $client->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Clients', 'url' => 'clients'],
                ['label' => $client->name, 'url' => 'clients/' . $client->id],
                ['label' => 'Edit'],
            ],
            'client' => $client,
            'assignees' => User::listAssignable($this->tenantId()),
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('clients.update');
        $client = $this->tenantClient($id);
        $data = $this->formData();

        if (!$this->validateClient($data, 'clients/' . $client->id . '/edit')) {
            return;
        }

        (new ClientService())->update($client, $data, $this->actor()->id);
        flash('success', 'Client has been updated.');
        $this->redirect('clients/' . $client->id);
    }

    public function activate(string $id): void
    {
        $this->changeStatus($id, 'active');
    }

    public function archive(string $id): void
    {
        $this->changeStatus($id, 'archived');
    }

    private function changeStatus(string $id, string $status): void
    {
        $this->authorize('clients.activate');
        $client = $this->tenantClient($id);
        (new ClientService())->setStatus($client, $status, $this->actor()->id);
        flash('success', 'Client status has been updated.');
        $this->redirect('clients/' . $client->id);
    }

    private function formData(): array
    {
        return Request::only([
            'name', 'contact_name', 'email', 'phone', 'address', 'city', 'country',
            'tax_id', 'tax_information', 'website', 'industry', 'status', 'notes',
            'assigned_user_id',
        ]);
    }

    private function validateClient(array $data, string $back): bool
    {
        $validator = new Validator($data);
        if ($validator->validate([
            'name' => 'required|max:180',
            'email' => 'email|max:190',
            'status' => 'required|in:lead,active,inactive,archived',
            'contact_name' => 'max:160',
            'phone' => 'max:50',
            'website' => 'max:255',
        ])) {
            return true;
        }

        $this->withErrors($validator->errors());
        $this->redirect($back);
        return false;
    }

    private function tenantClient(string $id): Client
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $client = Client::findInOrganization((int) $id, $this->tenantId());
        if (!$client instanceof Client || !CrmAccess::canViewClient($client)) {
            Response::notFound();
        }

        return $client;
    }
}
