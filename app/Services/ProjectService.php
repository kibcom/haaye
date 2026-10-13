<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\WorkflowPending;
use App\Services\StatusWorkflowService;

final class ProjectService
{
    public function create(int $organizationId, array $data, ?int $actorId): Project
    {
        $client = $this->client($organizationId, (int) $data['client_id']);
        $status = $this->status($organizationId, (string) $data['status']);
        $managerId = $this->userId($organizationId, $data['manager_user_id'] ?? null);
        $this->assertDates($data['start_date'] ?? null, $data['due_date'] ?? null);
        $code = $this->code($organizationId, $data['code'] ?? null, null);

        if ($managerId === null && $actorId !== null && !ProjectAccess::seesAll()) {
            $managerId = $actorId;
        }

        $now = date('Y-m-d H:i:s');
        $id = Project::create([
            'organization_id' => $organizationId,
            'client_id' => $client->id,
            'name' => $data['name'],
            'code' => $code,
            'description' => $data['description'] ?: null,
            'status' => $status->slug,
            'priority' => $data['priority'],
            'start_date' => $data['start_date'] ?: null,
            'due_date' => $data['due_date'] ?: null,
            'budget' => $this->money($data['budget'] ?? null),
            'billing_type' => $data['billing_type'],
            'manager_user_id' => $managerId,
            'progress' => 0,
            'payment_status' => $data['payment_status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($managerId !== null) {
            ProjectMember::upsert($organizationId, $id, $managerId, 'manager');
        }

        if ($actorId !== null && $actorId !== $managerId && !ProjectAccess::seesAll()) {
            ProjectMember::upsert($organizationId, $id, $actorId, 'manager');
        }

        ActivityLog::record($organizationId, $actorId, 'project.created', 'Project "' . $data['name'] . '" was created', 'project', $id);

        $project = Project::findInOrganization($id, $organizationId);
        if (!$project instanceof Project) {
            throw new \RuntimeException('Project could not be created.');
        }

        return $project;
    }

    public function update(Project $project, array $data, ?int $actorId): void
    {
        $client = $this->client((int) $project->organization_id, (int) $data['client_id']);
        $status = $this->status((int) $project->organization_id, (string) $data['status']);
        $pending = null;
        try {
            (new StatusWorkflowService())->gate(
                (int) $project->organization_id,
                'project',
                $project->status,
                $status->slug,
                (int) $project->id,
                (int) $project->id,
                (int) $project->client_id,
                (int) $actorId,
                $project->name
            );
        } catch (WorkflowPending $exception) {
            $pending = $exception;
        }
        $managerId = $this->userId((int) $project->organization_id, $data['manager_user_id'] ?? null);
        $this->assertDates($data['start_date'] ?? null, $data['due_date'] ?? null);
        $code = $this->code((int) $project->organization_id, $data['code'] ?? null, (int) $project->id);

        Project::updateById((int) $project->id, (int) $project->organization_id, [
            'client_id' => $client->id,
            'name' => $data['name'],
            'code' => $code,
            'description' => $data['description'] ?: null,
            'status' => $pending instanceof WorkflowPending ? $project->status : $status->slug,
            'priority' => $data['priority'],
            'start_date' => $data['start_date'] ?: null,
            'due_date' => $data['due_date'] ?: null,
            'budget' => $this->money($data['budget'] ?? null),
            'billing_type' => $data['billing_type'],
            'manager_user_id' => $managerId,
            'payment_status' => $data['payment_status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Project::syncProgress((int) $project->organization_id, (int) $project->id);

        if ($managerId !== null) {
            ProjectMember::upsert((int) $project->organization_id, (int) $project->id, $managerId, 'manager');
        }

        if ($pending === null && $status->slug !== $project->status) {
            (new ApprovalService())->withdrawPending(
                'project',
                (int) $project->id,
                (int) $project->organization_id,
                (int) $actorId,
                'Status moved to ' . $status->name . ' before the waiting approval was decided.'
            );
        }

        ActivityLog::record(
            (int) $project->organization_id,
            $actorId,
            'project.updated',
            'Project "' . $data['name'] . '" was updated',
            'project',
            (int) $project->id
        );
        if ($pending instanceof WorkflowPending) {
            throw $pending;
        }
    }

    public function addMember(Project $project, int $userId, string $role, ?int $actorId): void
    {
        $this->addMembers($project, [$userId], $role, $actorId);
    }

    /** @param list<int|string> $userIds */
    public function addMembers(Project $project, array $userIds, string $role, ?int $actorId): int
    {
        $users = [];
        foreach ($userIds as $userId) {
            $user = User::findInOrganization((int) $userId, (int) $project->organization_id);
            if (!$user instanceof User) {
                throw new \RuntimeException('One of the selected people is not in this organization.');
            }
            $users[] = $user;
        }

        foreach ($users as $user) {
            ProjectMember::upsert((int) $project->organization_id, (int) $project->id, (int) $user->id, $role);
            ActivityLog::record(
                (int) $project->organization_id,
                $actorId,
                'project.member_added',
                $user->name . ' was added to "' . $project->name . '"',
                'project',
                (int) $project->id
            );
        }

        return count($users);
    }

    public function updateMemberRole(Project $project, ProjectMember $member, string $role, ?int $actorId): void
    {
        if (!in_array($role, ['manager', 'member'], true)) {
            throw new \RuntimeException('Choose member or manager.');
        }

        ProjectMember::upsert((int) $project->organization_id, (int) $project->id, (int) $member->user_id, $role);
        ActivityLog::record(
            (int) $project->organization_id,
            $actorId,
            'project.member_updated',
            ($member->user_name ?: 'A member') . ' is now a ' . $role . ' on "' . $project->name . '"',
            'project',
            (int) $project->id
        );
    }

    public function removeMember(Project $project, ProjectMember $member, ?int $actorId): void
    {
        if ((int) $project->manager_user_id === (int) $member->user_id) {
            throw new \RuntimeException('Change the project manager before removing that person.');
        }

        ProjectMember::deleteInProject((int) $member->id, (int) $project->id, (int) $project->organization_id);
        ActivityLog::record(
            (int) $project->organization_id,
            $actorId,
            'project.member_removed',
            ($member->user_name ?: 'A member') . ' was removed from "' . $project->name . '"',
            'project',
            (int) $project->id
        );
    }

    public function saveMilestone(Project $project, ?Milestone $milestone, array $data, ?int $actorId): Milestone
    {
        $ownerId = $this->userId((int) $project->organization_id, $data['owner_user_id'] ?? null);
        $status = (string) $data['status'];
        $pending = null;
        if ($milestone instanceof Milestone && $milestone->status !== $status && $actorId !== null) {
            try {
                (new StatusWorkflowService())->gate(
                    (int) $project->organization_id,
                    'milestone',
                    $milestone->status,
                    $status,
                    (int) $milestone->id,
                    (int) $project->id,
                    (int) $project->client_id,
                    $actorId,
                    $milestone->name
                );
            } catch (WorkflowPending $exception) {
                $pending = $exception;
                $status = $milestone->status;
            }
        }
        $progress = $this->progress($data['progress'] ?? 0);
        $completedAt = $milestone?->completed_at;

        if ($status === 'completed') {
            $progress = 100;
            $completedAt = $completedAt ?: date('Y-m-d H:i:s');
        } else {
            $completedAt = null;
        }

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?: null,
            'due_date' => $data['due_date'] ?: null,
            'status' => $status,
            'progress' => $progress,
            'owner_user_id' => $ownerId,
            'completed_at' => $completedAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($milestone instanceof Milestone) {
            Milestone::updateById((int) $milestone->id, (int) $project->organization_id, $payload);
            $id = (int) $milestone->id;
            $action = 'project.milestone_updated';
            $message = 'Milestone "' . $data['name'] . '" was updated';
        } else {
            $payload['organization_id'] = $project->organization_id;
            $payload['project_id'] = $project->id;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $id = Milestone::create($payload);
            $action = 'project.milestone_created';
            $message = 'Milestone "' . $data['name'] . '" was added';
        }

        ActivityLog::record((int) $project->organization_id, $actorId, $action, $message, 'project', (int) $project->id);

        $saved = Milestone::findInProject($id, (int) $project->id, (int) $project->organization_id);
        if (!$saved instanceof Milestone) {
            throw new \RuntimeException('Milestone could not be saved.');
        }

        if ($pending instanceof WorkflowPending) {
            throw $pending;
        }

        return $saved;
    }

    public function deleteMilestone(Project $project, Milestone $milestone, ?int $actorId): void
    {
        Milestone::deleteInProject((int) $milestone->id, (int) $project->id, (int) $project->organization_id);
        ActivityLog::record(
            (int) $project->organization_id,
            $actorId,
            'project.milestone_deleted',
            'Milestone "' . $milestone->name . '" was removed',
            'project',
            (int) $project->id
        );
    }

    private function client(int $organizationId, int $clientId): Client
    {
        $client = Client::findInOrganization($clientId, $organizationId);
        if (!$client instanceof Client) {
            throw new \RuntimeException('Select a client in this organization.');
        }

        return $client;
    }

    private function status(int $organizationId, string $slug): ProjectStatus
    {
        ProjectStatus::ensureDefaults($organizationId);
        $status = ProjectStatus::findBySlug($organizationId, $slug);
        if (!$status instanceof ProjectStatus) {
            throw new \RuntimeException('Select a valid project status.');
        }

        return $status;
    }

    private function userId(int $organizationId, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $user = User::findInOrganization((int) $userId, $organizationId);
        if (!$user instanceof User) {
            throw new \RuntimeException('Selected person is not in this organization.');
        }

        return (int) $user->id;
    }

    private function code(int $organizationId, mixed $code, ?int $exceptId): ?string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        if (Project::codeExists($organizationId, $code, $exceptId)) {
            throw new \RuntimeException('That project code is already in use.');
        }

        return $code;
    }

    private function assertDates(mixed $start, mixed $due): void
    {
        if ($start && $due && strtotime((string) $due) < strtotime((string) $start)) {
            throw new \RuntimeException('Due date must be on or after the start date.');
        }
    }

    private function progress(mixed $value): int
    {
        $progress = (int) $value;
        if ($progress < 0 || $progress > 100) {
            throw new \RuntimeException('Progress must be between 0 and 100.');
        }

        return $progress;
    }

    private function money(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new \RuntimeException('Budget must be a positive amount.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
