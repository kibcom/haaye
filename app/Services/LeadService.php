<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\User;

final class LeadService
{
    /** @var list<string> */
    public const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'lost'];

    /** @var list<string> */
    public const SOURCES = ['website', 'referral', 'call', 'email', 'event', 'other'];

    public function create(int $organizationId, array $data, int $actorId): Lead
    {
        $now = date('Y-m-d H:i:s');
        $id = Lead::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'company' => $data['company'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'source' => $data['source'],
            'status' => $data['status'],
            'estimated_value' => $this->money($data['estimated_value'] ?? null),
            'notes' => $data['notes'] ?: null,
            'owner_user_id' => $this->owner($organizationId, $data['owner_user_id'] ?? null) ?? $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        ActivityLog::record($organizationId, $actorId, 'lead.created', 'Lead "' . $data['name'] . '" was added', 'lead', $id);

        return $this->saved($id, $organizationId);
    }

    public function setStage(Lead $lead, string $status, int $actorId): void
    {
        if ($lead->status === 'converted') {
            throw new \RuntimeException('A converted lead is kept as the history of the client and project.');
        }
        if (!in_array($status, ['new', 'contacted', 'qualified', 'lost'], true)) {
            throw new \RuntimeException('Choose a stage.');
        }
        if ($status === $lead->status) {
            return;
        }

        Lead::updateById((int) $lead->id, (int) $lead->organization_id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record(
            (int) $lead->organization_id,
            $actorId,
            'lead.updated',
            'Lead "' . $lead->name . '" moved to ' . $status,
            'lead',
            (int) $lead->id
        );
    }

    public function update(Lead $lead, array $data, int $actorId): void
    {
        if ($lead->status === 'converted') {
            throw new \RuntimeException('A converted lead is kept as the history of the client and project.');
        }

        Lead::updateById((int) $lead->id, (int) $lead->organization_id, [
            'name' => $data['name'],
            'company' => $data['company'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'source' => $data['source'],
            'status' => $data['status'] === 'converted' ? $lead->status : $data['status'],
            'estimated_value' => $this->money($data['estimated_value'] ?? null),
            'notes' => $data['notes'] ?: null,
            'owner_user_id' => $this->owner((int) $lead->organization_id, $data['owner_user_id'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $lead->organization_id, $actorId, 'lead.updated', 'Lead "' . $data['name'] . '" was updated', 'lead', (int) $lead->id);
    }

    public function convert(Lead $lead, array $data, int $actorId): Lead
    {
        if ($lead->client_id !== null) {
            throw new \RuntimeException('This lead is already linked to a client.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $converted = $this->convertNow($lead, $data, $actorId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('The lead could not be converted.');
        }

        return $converted;
    }

    private function convertNow(Lead $lead, array $data, int $actorId): Lead
    {
        $organizationId = (int) $lead->organization_id;
        $company = trim((string) ($lead->company !== null && $lead->company !== '' ? $lead->company : $lead->name));
        $client = (new ClientService())->create($organizationId, [
            'name' => $company,
            'contact_name' => $lead->name,
            'email' => $lead->email ?: '',
            'phone' => $lead->phone ?: '',
            'address' => '',
            'city' => '',
            'country' => '',
            'tax_id' => '',
            'tax_information' => '',
            'website' => '',
            'industry' => '',
            'status' => 'active',
            'notes' => $lead->notes ?: '',
            'assigned_user_id' => $lead->owner_user_id,
        ], $actorId);

        $projectId = null;
        if (($data['open_project'] ?? '') === '1') {
            $projectName = trim((string) ($data['project_name'] ?? ''));
            if ($projectName === '') {
                $projectName = $company;
            }
            $project = (new ProjectService())->create($organizationId, [
                'client_id' => (int) $client->id,
                'name' => $projectName,
                'code' => '',
                'description' => $lead->notes ?: '',
                'status' => 'planning',
                'priority' => 'medium',
                'start_date' => date('Y-m-d'),
                'due_date' => '',
                'budget' => $lead->estimated_value,
                'billing_type' => 'fixed',
                'payment_status' => 'unpaid',
                'manager_user_id' => $lead->owner_user_id ?: $actorId,
            ], $actorId);
            $projectId = (int) $project->id;
        }

        Lead::updateById((int) $lead->id, $organizationId, [
            'status' => 'converted',
            'client_id' => (int) $client->id,
            'project_id' => $projectId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record($organizationId, $actorId, 'lead.converted', 'Lead "' . $lead->name . '" became client "' . $client->name . '"', 'lead', (int) $lead->id);

        return $this->saved((int) $lead->id, $organizationId);
    }

    public function delete(Lead $lead, int $actorId): void
    {
        Lead::deleteInOrganization((int) $lead->id, (int) $lead->organization_id);
        ActivityLog::record((int) $lead->organization_id, $actorId, 'lead.deleted', 'Lead "' . $lead->name . '" was removed', 'lead', (int) $lead->id);
    }

    private function saved(int $id, int $organizationId): Lead
    {
        $lead = Lead::findInOrganization($id, $organizationId);
        if (!$lead instanceof Lead) {
            throw new \RuntimeException('Lead could not be saved.');
        }

        return $lead;
    }

    private function owner(int $organizationId, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $user = User::findInOrganization((int) $userId, $organizationId);
        if (!$user instanceof User) {
            throw new \RuntimeException('Choose an owner from this company.');
        }

        return (int) $user->id;
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value < 0) {
            throw new \RuntimeException('Estimated value must be a positive amount.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
