<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Client;
use App\Models\ClientContact;
use App\Services\ClientService;

final class ClientContactController extends Controller
{
    public function edit(string $clientId, string $contactId): void
    {
        $this->authorize('clients.contacts.manage');
        $client = $this->tenantClient($clientId);
        $contact = $this->tenantContact($contactId, $client);

        $this->view('clients/contact-form', [
            'title' => 'Edit contact',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Clients', 'url' => 'clients'],
                ['label' => $client->name, 'url' => 'clients/' . $client->id],
                ['label' => 'Edit contact'],
            ],
            'client' => $client,
            'contact' => $contact,
        ]);
    }

    public function store(string $clientId): void
    {
        $this->authorize('clients.contacts.manage');
        $client = $this->tenantClient($clientId);
        $data = Request::only(['name', 'email', 'phone', 'job_title', 'notes', 'is_primary']);

        $validator = new Validator($data);
        if (!$validator->validate([
            'name' => 'required|max:160',
            'email' => 'email|max:190',
            'phone' => 'max:50',
            'job_title' => 'max:120',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('clients/' . $client->id);
        }

        (new ClientService())->addContact($client, $data, $this->actor()->id);
        flash('success', 'Contact has been added.');
        $this->redirect('clients/' . $client->id);
    }

    public function update(string $clientId, string $contactId): void
    {
        $this->authorize('clients.contacts.manage');
        $client = $this->tenantClient($clientId);
        $contact = $this->tenantContact($contactId, $client);
        $data = Request::only(['name', 'email', 'phone', 'job_title', 'notes', 'is_primary']);

        $validator = new Validator($data);
        if (!$validator->validate([
            'name' => 'required|max:160',
            'email' => 'email|max:190',
            'phone' => 'max:50',
            'job_title' => 'max:120',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('clients/' . $client->id);
        }

        (new ClientService())->updateContact($client, $contact, $data, $this->actor()->id);
        flash('success', 'Contact has been updated.');
        $this->redirect('clients/' . $client->id);
    }

    public function destroy(string $clientId, string $contactId): void
    {
        $this->authorize('clients.contacts.manage');
        $client = $this->tenantClient($clientId);
        $contact = $this->tenantContact($contactId, $client);
        (new ClientService())->deleteContact($client, $contact, $this->actor()->id);
        flash('success', 'Contact has been removed.');
        $this->redirect('clients/' . $client->id);
    }

    public function primary(string $clientId, string $contactId): void
    {
        $this->authorize('clients.contacts.manage');
        $client = $this->tenantClient($clientId);
        $contact = $this->tenantContact($contactId, $client);
        (new ClientService())->markPrimary($client, $contact, $this->actor()->id);
        flash('success', 'Primary contact has been updated.');
        $this->redirect('clients/' . $client->id);
    }

    private function tenantClient(string $id): Client
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $client = Client::findInOrganization((int) $id, $this->tenantId());
        if (!$client instanceof Client) {
            Response::notFound();
        }

        return $client;
    }

    private function tenantContact(string $id, Client $client): ClientContact
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $contact = ClientContact::findInOrganization((int) $id, (int) $client->id, $this->tenantId());
        if (!$contact instanceof ClientContact) {
            Response::notFound();
        }

        return $contact;
    }
}
