<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkFlowRole;
use App\Models\WorkFlowTask;

final class BookingService
{
    /** @var list<string> */
    public const STATUSES = ['scheduled', 'confirmed', 'completed', 'cancelled'];

    public function create(int $organizationId, array $data, int $actorId, ?int $onlyUserId): Booking
    {
        Phase12Setup::applySchema();
        Phase13Setup::applySchema();
        $project = $this->project($organizationId, (int) $data['project_id'], $onlyUserId);
        [$starts, $ends] = $this->window($data['starts_at'] ?? '', $data['ends_at'] ?? '');
        $checklist = BookingChecklist::parseInput($data);
        $company = $project->client_name ?: $checklist['checklist_company'];
        $now = date('Y-m-d H:i:s');

        $id = Booking::create([
            'organization_id' => $organizationId,
            'project_id' => (int) $project->id,
            'task_id' => null,
            'title' => $data['title'],
            'contact_name' => $data['contact_name'] ?: null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => $data['status'],
            'location' => $data['location'] ?: null,
            'notes' => $data['notes'] ?: null,
            'checklist_company' => $company,
            'checklist_date' => $checklist['checklist_date'] ?: substr($starts, 0, 10),
            'equipment_json' => $checklist['equipment_json'],
            'rentals_json' => $checklist['rentals_json'],
            'represented_name' => $checklist['represented_name'],
            'represented_signed' => $checklist['represented_signed'],
            'checklist_status' => 'draft',
            'selected_equipment_json' => $checklist['selected_equipment_json'],
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($checklist['create_linked_task']) {
            $taskId = $this->createLinkedTask($organizationId, $project, $data, $checklist, $actorId, $id, $starts);
            Booking::updateById($id, $organizationId, [
                'task_id' => $taskId,
                'updated_at' => $now,
            ]);
            WorkFlowTask::updateById($taskId, $organizationId, [
                'booking_id' => $id,
                'updated_at' => $now,
            ]);
        }

        $booking = $this->saved($id, $organizationId);
        if ($checklist['submit_now']) {
            $this->submitForApproval($booking, (int) $checklist['submit_to_user_id'], $actorId);
            $booking = $this->saved($id, $organizationId);
        }

        ActivityLog::record($organizationId, $actorId, 'booking.created', 'Booking "' . $data['title'] . '" was added to ' . $project->name, 'booking', $id);

        return $booking;
    }

    public function update(Booking $booking, array $data, int $actorId, ?int $onlyUserId): void
    {
        Phase12Setup::applySchema();
        Phase13Setup::applySchema();
        $project = $this->project((int) $booking->organization_id, (int) $data['project_id'], $onlyUserId);
        [$starts, $ends] = $this->window($data['starts_at'] ?? '', $data['ends_at'] ?? '');
        $checklist = BookingChecklist::parseInput($data);
        $now = date('Y-m-d H:i:s');

        $payload = [
            'project_id' => (int) $project->id,
            'title' => $data['title'],
            'contact_name' => $data['contact_name'] ?: null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'status' => $data['status'],
            'location' => $data['location'] ?: null,
            'notes' => $data['notes'] ?: null,
            'checklist_company' => $project->client_name ?: $checklist['checklist_company'],
            'checklist_date' => $checklist['checklist_date'] ?: substr($starts, 0, 10),
            'equipment_json' => $checklist['equipment_json'],
            'rentals_json' => $checklist['rentals_json'],
            'represented_name' => $checklist['represented_name'],
            'represented_signed' => $checklist['represented_signed'],
            'selected_equipment_json' => $checklist['selected_equipment_json'],
            'updated_at' => $now,
        ];

        if ($checklist['create_linked_task'] && !(int) ($booking->task_id ?? 0)) {
            $taskId = $this->createLinkedTask(
                (int) $booking->organization_id,
                $project,
                $data,
                $checklist,
                $actorId,
                (int) $booking->id,
                $starts
            );
            $payload['task_id'] = $taskId;
            WorkFlowTask::updateById($taskId, (int) $booking->organization_id, [
                'booking_id' => (int) $booking->id,
                'updated_at' => $now,
            ]);
        }

        Booking::updateById((int) $booking->id, (int) $booking->organization_id, $payload);
        ActivityLog::record((int) $booking->organization_id, $actorId, 'booking.updated', 'Booking "' . $data['title'] . '" was updated', 'booking', (int) $booking->id);

        if ($checklist['submit_now']) {
            $fresh = $this->saved((int) $booking->id, (int) $booking->organization_id);
            $this->submitForApproval($fresh, (int) $checklist['submit_to_user_id'], $actorId);
        }
    }

    public function submitForApproval(Booking $booking, int $approverUserId, int $actorId): void
    {
        Phase13Setup::applySchema();
        if ($approverUserId < 1) {
            throw new \RuntimeException('Choose a CEO or production manager to submit to.');
        }
        $approver = User::findInOrganization($approverUserId, (int) $booking->organization_id);
        if (!$approver instanceof User || $approver->status !== 'active') {
            throw new \RuntimeException('Choose an active approver.');
        }
        $slug = (string) ($approver->role_slug ?? '');
        if (!in_array($slug, ['general_manager', 'operations_manager', 'owner', 'admin', 'manager'], true)) {
            throw new \RuntimeException('Submit only to CEO (General Manager) or Production / Operations Manager.');
        }

        $now = date('Y-m-d H:i:s');
        Booking::updateById((int) $booking->id, (int) $booking->organization_id, [
            'checklist_status' => 'pending_approval',
            'submitted_to_user_id' => $approverUserId,
            'submitted_at' => $now,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'updated_at' => $now,
        ]);

        NotificationService::notifyUser(
            (int) $booking->organization_id,
            $approverUserId,
            'Booking checklist needs approval',
            $booking->title . ' · ' . ($booking->checklist_company ?: $booking->client_name ?: 'Company'),
            'bookings/' . $booking->id,
            $actorId
        );
        ActivityLog::record(
            (int) $booking->organization_id,
            $actorId,
            'booking.submitted',
            'Submitted booking checklist "' . $booking->title . '" to ' . $approver->name . '.',
            'booking',
            (int) $booking->id
        );
    }

    public function review(Booking $booking, string $decision, ?string $note, int $actorId): void
    {
        Phase13Setup::applySchema();
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new \RuntimeException('Choose approve or reject.');
        }
        if (($booking->checklist_status ?? '') !== 'pending_approval') {
            throw new \RuntimeException('This checklist is not waiting for approval.');
        }
        if ((int) ($booking->submitted_to_user_id ?? 0) !== $actorId
            && !Auth::can('bookings.approve')
            && !Auth::can('work_flow.manage')) {
            throw new \RuntimeException('Only the assigned approver can review this checklist.');
        }

        $now = date('Y-m-d H:i:s');
        $cleanNote = trim((string) $note);
        Booking::updateById((int) $booking->id, (int) $booking->organization_id, [
            'checklist_status' => $decision,
            'reviewed_by' => $actorId,
            'reviewed_at' => $now,
            'review_note' => $cleanNote !== '' ? mb_substr($cleanNote, 0, 255) : null,
            'updated_at' => $now,
        ]);

        if ((int) ($booking->created_by ?? 0) > 0) {
            NotificationService::notifyUser(
                (int) $booking->organization_id,
                (int) $booking->created_by,
                'Booking checklist ' . $decision,
                $booking->title,
                'bookings/' . $booking->id,
                $actorId
            );
        }
        ActivityLog::record(
            (int) $booking->organization_id,
            $actorId,
            'booking.reviewed',
            'Marked booking checklist "' . $booking->title . '" as ' . $decision . '.',
            'booking',
            (int) $booking->id
        );
    }

    public function setStatus(Booking $booking, string $status, int $actorId): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('Choose a valid booking status.');
        }
        Booking::updateById((int) $booking->id, (int) $booking->organization_id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $booking->organization_id, $actorId, 'booking.status', 'Booking "' . $booking->title . '" is ' . str_replace('_', ' ', $status), 'booking', (int) $booking->id);
    }

    public function delete(Booking $booking, int $actorId): void
    {
        Booking::deleteInOrganization((int) $booking->id, (int) $booking->organization_id);
        ActivityLog::record((int) $booking->organization_id, $actorId, 'booking.deleted', 'Booking "' . $booking->title . '" was removed', 'booking', (int) $booking->id);
    }

    /**
     * @param array{work_flow_role_id:int,assignee_user_id:int} $checklist
     */
    private function createLinkedTask(
        int $organizationId,
        Project $project,
        array $data,
        array $checklist,
        int $actorId,
        int $bookingId,
        string $starts
    ): int {
        $roleId = (int) ($checklist['work_flow_role_id'] ?? 0);
        $role = $roleId > 0 ? WorkFlowRole::findInOrganization($roleId, $organizationId) : null;
        $assigneeId = (int) ($checklist['assignee_user_id'] ?? 0);
        if ($assigneeId > 0) {
            $assignee = User::findInOrganization($assigneeId, $organizationId);
            if (!$assignee instanceof User || $assignee->status !== 'active') {
                throw new \RuntimeException('Choose an active teammate for the linked task.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $due = substr($starts, 0, 10);
        $taskId = WorkFlowTask::create([
            'organization_id' => $organizationId,
            'project_id' => (int) $project->id,
            'client_id' => (int) $project->client_id,
            'work_flow_role_id' => $role?->id,
            'booking_id' => $bookingId,
            'title' => (string) $data['title'],
            'description' => 'Booking quick-start checklist task'
                . ($checklist['checklist_company'] ? ' for ' . $checklist['checklist_company'] : '')
                . '.',
            'assignee_user_id' => $assigneeId > 0 ? $assigneeId : null,
            'status' => 'todo',
            'due_date' => $due,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ActivityLog::record(
            $organizationId,
            $actorId,
            'task.created_from_booking',
            'Created shared task from booking "' . $data['title'] . '".',
            'task',
            $taskId
        );

        return $taskId;
    }

    private function saved(int $id, int $organizationId): Booking
    {
        $booking = Booking::findInOrganization($id, $organizationId);
        if (!$booking instanceof Booking) {
            throw new \RuntimeException('Booking could not be saved.');
        }

        return $booking;
    }

    private function project(int $organizationId, int $projectId, ?int $onlyUserId): Project
    {
        $project = Project::findInOrganization($projectId, $organizationId);
        if (!$project instanceof Project) {
            throw new \RuntimeException('Choose a project in this company.');
        }
        if ($onlyUserId !== null && !ProjectAccess::canView($project)) {
            throw new \RuntimeException('Choose a project you can work on.');
        }

        return $project;
    }

    /** @return array{0:string,1:string} */
    private function window(mixed $start, mixed $end): array
    {
        $starts = strtotime((string) $start);
        $ends = strtotime((string) $end);
        if ($starts === false || $ends === false) {
            throw new \RuntimeException('Enter a start and end time.');
        }
        if ($ends <= $starts) {
            throw new \RuntimeException('The booking must end after it starts.');
        }

        return [date('Y-m-d H:i:s', $starts), date('Y-m-d H:i:s', $ends)];
    }
}
