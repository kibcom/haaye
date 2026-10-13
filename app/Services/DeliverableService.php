<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\FileUploader;
use App\Models\ActivityLog;
use App\Models\Deliverable;
use App\Models\DeliverableStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

final class DeliverableService
{
    public function create(int $organizationId, array $data, int $actorId): Deliverable
    {
        $project = $this->project($organizationId, (int) ($data['project_id'] ?? 0));
        if (!DeliverableAccess::canCreate($project)) {
            throw new \RuntimeException('You cannot add deliverables to this project.');
        }

        $status = $this->status($organizationId, (string) ($data['status'] ?? 'draft'));
        $taskId = $this->taskId($organizationId, (int) $project->id, $data['task_id'] ?? null);
        $now = date('Y-m-d H:i:s');
        $id = Deliverable::create([
            'organization_id' => $organizationId,
            'project_id' => $project->id,
            'task_id' => $taskId,
            'client_id' => $project->client_id,
            'name' => $data['name'],
            'description' => $this->text($data['description'] ?? null),
            'due_date' => $this->text($data['due_date'] ?? null),
            'assignee_user_id' => $this->assignee($organizationId, $data['assignee_user_id'] ?? null),
            'status' => $status->slug,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record($organizationId, $actorId, 'deliverable.created', 'Deliverable "' . $data['name'] . '" was created', 'deliverable', $id);
        $deliverable = Deliverable::findInOrganization($id, $organizationId);
        if (!$deliverable instanceof Deliverable) {
            throw new \RuntimeException('Deliverable could not be created.');
        }

        return $deliverable;
    }

    public function update(Deliverable $deliverable, array $data, int $actorId): void
    {
        if (!DeliverableAccess::canUpdate($deliverable)) {
            throw new \RuntimeException('You cannot edit this deliverable.');
        }

        $organizationId = (int) $deliverable->organization_id;
        $project = $this->project($organizationId, (int) ($data['project_id'] ?? $deliverable->project_id));
        if ((int) $project->id !== (int) $deliverable->project_id && !DeliverableAccess::canCreate($project)) {
            throw new \RuntimeException('Choose a project you can access.');
        }

        $status = $this->status($organizationId, (string) ($data['status'] ?? $deliverable->status));
        $pending = $this->gate($deliverable, $status->slug, $actorId, (int) $project->id, (int) $project->client_id);
        $taskId = $this->taskId($organizationId, (int) $project->id, $data['task_id'] ?? null);

        Deliverable::updateById((int) $deliverable->id, $organizationId, [
            'project_id' => $project->id,
            'task_id' => $taskId,
            'client_id' => $project->client_id,
            'name' => $data['name'],
            'description' => $this->text($data['description'] ?? null),
            'due_date' => $this->text($data['due_date'] ?? null),
            'assignee_user_id' => $this->assignee($organizationId, $data['assignee_user_id'] ?? null),
            'status' => $pending ? $deliverable->status : $status->slug,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLog::record($organizationId, $actorId, 'deliverable.updated', 'Deliverable "' . $data['name'] . '" was updated', 'deliverable', (int) $deliverable->id);
        if ($pending === null && $status->slug !== $deliverable->status) {
            (new ApprovalService())->withdrawPending(
                'deliverable',
                (int) $deliverable->id,
                $organizationId,
                $actorId,
                'Status moved to ' . $status->name . ' before the waiting approval was decided.'
            );
        }
        if ($pending instanceof WorkflowPending) {
            throw $pending;
        }
    }

    public function changeStatus(Deliverable $deliverable, string $statusSlug, int $actorId): void
    {
        if (!DeliverableAccess::canUpdate($deliverable)) {
            throw new \RuntimeException('You cannot update this deliverable.');
        }

        $status = $this->status((int) $deliverable->organization_id, $statusSlug);
        $pending = $this->gate($deliverable, $status->slug, $actorId, (int) $deliverable->project_id, (int) $deliverable->client_id);
        if ($pending instanceof WorkflowPending) {
            throw $pending;
        }

        Deliverable::updateById((int) $deliverable->id, (int) $deliverable->organization_id, [
            'status' => $status->slug,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($status->slug !== $deliverable->status) {
            (new ApprovalService())->withdrawPending(
                'deliverable',
                (int) $deliverable->id,
                (int) $deliverable->organization_id,
                $actorId,
                'Status moved to ' . $status->name . ' before the waiting approval was decided.'
            );
        }
        ActivityLog::record(
            (int) $deliverable->organization_id,
            $actorId,
            'deliverable.status_changed',
            'Deliverable "' . $deliverable->name . '" moved to ' . $status->name,
            'deliverable',
            (int) $deliverable->id
        );
    }

    public function storeFile(Deliverable $deliverable, array $file, int $actorId): void
    {
        if (!DeliverableAccess::canUpdate($deliverable)) {
            throw new \RuntimeException('You cannot update this deliverable.');
        }

        $stored = FileUploader::privateDocument($file, 'deliverables/' . (int) $deliverable->organization_id);
        if ($stored === null) {
            throw new \RuntimeException('Choose a file to upload.');
        }

        if ($deliverable->stored_path) {
            FileUploader::deletePrivate($deliverable->stored_path);
        }

        $version = max(1, (int) $deliverable->version);
        if ($deliverable->stored_path) {
            $version++;
        }

        Deliverable::updateById((int) $deliverable->id, (int) $deliverable->organization_id, [
            'original_name' => $stored['original_name'],
            'stored_path' => $stored['stored_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'version' => $version,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record(
            (int) $deliverable->organization_id,
            $actorId,
            'deliverable.file_added',
            'Version ' . $version . ' uploaded for "' . $deliverable->name . '"',
            'deliverable',
            (int) $deliverable->id
        );
        if (in_array($deliverable->status, ['client_review', 'changes_requested', 'approved', 'delivered'], true)) {
            NotificationService::notifyClient(
                (int) $deliverable->organization_id,
                (int) $deliverable->client_id,
                'A file was shared',
                $deliverable->name . ' has a new file.',
                'deliverables/' . (int) $deliverable->id
            );
        }
    }

    public function delete(Deliverable $deliverable, int $actorId): void
    {
        if (!DeliverableAccess::canUpdate($deliverable)) {
            throw new \RuntimeException('You cannot remove this deliverable.');
        }

        if ($deliverable->stored_path) {
            FileUploader::deletePrivate($deliverable->stored_path);
        }

        $name = $deliverable->name;
        $id = (int) $deliverable->id;
        $orgId = (int) $deliverable->organization_id;
        Deliverable::deleteInOrganization($id, $orgId);
        ActivityLog::record($orgId, $actorId, 'deliverable.deleted', 'Deliverable "' . $name . '" was removed', 'deliverable', $id);
    }

    private function gate(Deliverable $deliverable, string $to, int $actorId, int $projectId, int $clientId): ?WorkflowPending
    {
        try {
            (new StatusWorkflowService())->gate(
                (int) $deliverable->organization_id,
                'deliverable',
                $deliverable->status,
                $to,
                (int) $deliverable->id,
                $projectId,
                $clientId,
                $actorId,
                $deliverable->name
            );
        } catch (WorkflowPending $pending) {
            return $pending;
        }

        return null;
    }

    private function project(int $organizationId, int $projectId): Project
    {
        $project = Project::findInOrganization($projectId, $organizationId);
        if (!$project instanceof Project) {
            throw new \RuntimeException('Choose a project in this organization.');
        }

        return $project;
    }

    private function status(int $organizationId, string $slug): DeliverableStatus
    {
        $status = DeliverableStatus::findBySlug($organizationId, $slug);
        if (!$status instanceof DeliverableStatus) {
            throw new \RuntimeException('Choose a valid deliverable status.');
        }

        return $status;
    }

    private function taskId(int $organizationId, int $projectId, $taskId): ?int
    {
        if ($taskId === null || $taskId === '' || (int) $taskId === 0) {
            return null;
        }

        $task = Task::findInOrganization((int) $taskId, $organizationId);
        if (!$task instanceof Task || (int) $task->project_id !== $projectId) {
            throw new \RuntimeException('Choose a task from the same project.');
        }

        return (int) $task->id;
    }

    private function assignee(int $organizationId, $userId): ?int
    {
        if ($userId === null || $userId === '' || (int) $userId === 0) {
            return null;
        }

        $user = User::find((int) $userId);
        if (!$user instanceof User || (int) $user->organization_id !== $organizationId || $user->status !== 'active') {
            throw new \RuntimeException('Assignee must be an active person in this organization.');
        }

        $role = $user->role();
        if ($role !== null && $role->slug === 'client') {
            throw new \RuntimeException('Client accounts cannot be assigned deliverables.');
        }

        return (int) $user->id;
    }

    private function text($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
