<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;

final class ClientService
{
    public function create(int $organizationId, array $data, ?int $actorId = null): Client
    {
        $now = date('Y-m-d H:i:s');
        $assignedUserId = $this->resolveAssignee($organizationId, $data['assigned_user_id'] ?? null)
            ?? ($actorId !== null && $actorId > 0 ? $actorId : null);

        $id = Client::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null,
            'country' => $data['country'] ?: null,
            'tax_id' => $data['tax_id'] ?: null,
            'tax_information' => $data['tax_information'] ?: null,
            'website' => $data['website'] ?: null,
            'industry' => $data['industry'] ?: null,
            'status' => $data['status'] ?? 'lead',
            'notes' => $data['notes'] ?: null,
            'assigned_user_id' => $assignedUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!empty($data['contact_name'])) {
            ClientContact::create([
                'organization_id' => $organizationId,
                'client_id' => $id,
                'name' => $data['contact_name'],
                'email' => $data['email'] ?: null,
                'phone' => $data['phone'] ?: null,
                'job_title' => $data['contact_job_title'] ?? 'Primary contact',
                'is_primary' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        ActivityLog::record($organizationId, $actorId, 'client.created', 'Client "' . $data['name'] . '" was created', 'client', $id);

        $client = Client::findInOrganization($id, $organizationId);
        if (!$client instanceof Client) {
            throw new \RuntimeException('Client could not be created.');
        }

        return $client;
    }

    public function update(Client $client, array $data, ?int $actorId = null): void
    {
        $assignedUserId = $this->resolveAssignee((int) $client->organization_id, $data['assigned_user_id'] ?? null);

        Client::updateById((int) $client->id, (int) $client->organization_id, [
            'name' => $data['name'],
            'contact_name' => $data['contact_name'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'city' => $data['city'] ?: null,
            'country' => $data['country'] ?: null,
            'tax_id' => $data['tax_id'] ?: null,
            'tax_information' => $data['tax_information'] ?: null,
            'website' => $data['website'] ?: null,
            'industry' => $data['industry'] ?: null,
            'status' => $data['status'] ?? $client->status,
            'notes' => $data['notes'] ?: null,
            'assigned_user_id' => $assignedUserId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record(
            (int) $client->organization_id,
            $actorId,
            'client.updated',
            'Client "' . $data['name'] . '" was updated',
            'client',
            (int) $client->id
        );
    }

    public function setStatus(Client $client, string $status, ?int $actorId = null): void
    {
        Client::updateById((int) $client->id, (int) $client->organization_id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record(
            (int) $client->organization_id,
            $actorId,
            'client.status_changed',
            'Client "' . $client->name . '" status changed to ' . $status,
            'client',
            (int) $client->id
        );
    }

    public function addContact(Client $client, array $data, ?int $actorId = null): ClientContact
    {
        $now = date('Y-m-d H:i:s');
        $makePrimary = !empty($data['is_primary']);

        if ($makePrimary) {
            ClientContact::clearPrimary((int) $client->id, (int) $client->organization_id);
        }

        $id = ClientContact::create([
            'organization_id' => $client->organization_id,
            'client_id' => $client->id,
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'job_title' => $data['job_title'] ?: null,
            'is_primary' => $makePrimary ? 1 : 0,
            'notes' => $data['notes'] ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($makePrimary) {
            $this->syncPrimaryName($client, $data['name'], $data['email'] ?? null, $data['phone'] ?? null);
        }

        ActivityLog::record(
            (int) $client->organization_id,
            $actorId,
            'client.contact_created',
            'Contact "' . $data['name'] . '" was added to ' . $client->name,
            'client',
            (int) $client->id
        );

        $contact = ClientContact::findInOrganization($id, (int) $client->id, (int) $client->organization_id);
        if (!$contact instanceof ClientContact) {
            throw new \RuntimeException('Contact could not be created.');
        }

        return $contact;
    }

    public function updateContact(Client $client, ClientContact $contact, array $data, ?int $actorId = null): void
    {
        $makePrimary = !empty($data['is_primary']);
        if ($makePrimary) {
            ClientContact::clearPrimary((int) $client->id, (int) $client->organization_id);
        }

        ClientContact::updateById((int) $contact->id, (int) $client->organization_id, [
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'job_title' => $data['job_title'] ?: null,
            'is_primary' => $makePrimary ? 1 : 0,
            'notes' => $data['notes'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($makePrimary) {
            $this->syncPrimaryName($client, $data['name'], $data['email'] ?? null, $data['phone'] ?? null);
        }

        ActivityLog::record(
            (int) $client->organization_id,
            $actorId,
            'client.contact_updated',
            'Contact "' . $data['name'] . '" was updated for ' . $client->name,
            'client',
            (int) $client->id
        );
    }

    public function deleteContact(Client $client, ClientContact $contact, ?int $actorId = null): void
    {
        ClientContact::deleteInOrganization((int) $contact->id, (int) $client->id, (int) $client->organization_id);
        ActivityLog::record(
            (int) $client->organization_id,
            $actorId,
            'client.contact_deleted',
            'Contact "' . $contact->name . '" was removed from ' . $client->name,
            'client',
            (int) $client->id
        );
    }

    public function markPrimary(Client $client, ClientContact $contact, ?int $actorId = null): void
    {
        ClientContact::clearPrimary((int) $client->id, (int) $client->organization_id);
        ClientContact::updateById((int) $contact->id, (int) $client->organization_id, [
            'is_primary' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->syncPrimaryName($client, $contact->name, $contact->email, $contact->phone);

        ActivityLog::record(
            (int) $client->organization_id,
            $actorId,
            'client.contact_primary',
            $contact->name . ' is now the primary contact for ' . $client->name,
            'client',
            (int) $client->id
        );
    }

    private function syncPrimaryName(Client $client, string $name, ?string $email, ?string $phone): void
    {
        Client::updateById((int) $client->id, (int) $client->organization_id, [
            'contact_name' => $name,
            'email' => $email ?: $client->email,
            'phone' => $phone ?: $client->phone,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function resolveAssignee(int $organizationId, mixed $assignedUserId): ?int
    {
        if ($assignedUserId === null || $assignedUserId === '') {
            return null;
        }

        $user = User::findInOrganization((int) $assignedUserId, $organizationId);
        return $user instanceof User ? (int) $user->id : null;
    }
}
