<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\Approval;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\ProjectAccess;
use App\Services\TaskAccess;
use App\Services\ApprovalService;
use App\Services\TimeAccess;
use App\Services\TimeService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (Auth::isSuperAdmin()) {
            $this->redirect('platform');
        }

        $this->authorize('dashboard.view');
        $this->pulseDailyTaskNotifications();

        $organization = $this->currentOrganization();
        $orgId = $this->tenantId();
        $role = Auth::role();
        $finance = Auth::can('finance.view')
            ? Invoice::metrics($orgId, ProjectAccess::restrictToUserId())
            : null;
        $taskCounts = Auth::can('tasks.view')
            ? Task::countByStatus($orgId, TaskAccess::restrictToUserId())
            : [];
        $weekStart = new \DateTimeImmutable('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $agenda = (Auth::can('tasks.view') && feature_enabled('tasks'))
            ? array_values(array_filter(
                Task::forCalendar($orgId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')),
                static fn (Task $task): bool => TaskAccess::canView($task)
            ))
            : [];

        $today = $this->organizationToday();
        if ($role !== null && $role->slug === 'client') {
            $this->clientHome($organization, $orgId, $today);
            return;
        }

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'breadcrumbs' => [['label' => 'Dashboard']],
            'organization' => $organization,
            'role' => $role,
            'userCount' => User::countByOrganization($orgId),
            'activeUserCount' => User::countByOrganization($orgId, 'active'),
            'inactiveUserCount' => User::countByOrganization($orgId, 'inactive'),
            'clientCount' => Client::countByOrganization($orgId),
            'activeClientCount' => Client::countByOrganization($orgId, 'active'),
            'leadClientCount' => Client::countByOrganization($orgId, 'lead'),
            'projectCount' => (Auth::can('projects.view') || Auth::can('projects.view_all'))
                ? Project::countVisible($orgId, ProjectAccess::restrictToUserId())
                : 0,
            'openProjectCount' => (Auth::can('projects.view') || Auth::can('projects.view_all'))
                ? Project::countVisible($orgId, ProjectAccess::restrictToUserId(), true)
                : 0,
            'openTaskCount' => Auth::can('tasks.view')
                ? Task::countVisible($orgId, TaskAccess::restrictToUserId(), true)
                : 0,
            'myOpenTaskCount' => Auth::can('tasks.view')
                ? Task::countVisible($orgId, TaskAccess::restrictToUserId(), true, Auth::id())
                : 0,
            'pendingApprovalCount' => (Auth::can('approvals.view') || Auth::can('approvals.client') || Auth::can('tasks.approve'))
                ? Approval::countPending($orgId, ApprovalService::viewerClientId(), ApprovalService::projectRestriction())
                : 0,
            'activePipelineCount' => (Auth::can('pipeline.view') && feature_enabled('pipeline'))
                ? \App\Models\WorkflowRun::countActive($orgId)
                : 0,
            'overdueTaskCount' => Auth::can('tasks.view')
                ? (int) \App\Core\Database::query(
                    'SELECT COUNT(*) FROM tasks t
                     LEFT JOIN task_statuses ts ON ts.organization_id = t.organization_id AND ts.slug = t.status
                     WHERE t.organization_id = ? AND t.due_date IS NOT NULL AND t.due_date < ?
                       AND COALESCE(ts.is_closed, 0) = 0'
                    . (TaskAccess::restrictToUserId() ? ' AND t.assignee_user_id = ' . (int) Auth::id() : ''),
                    [$orgId, $today]
                )->fetchColumn()
                : 0,
            'weekMinutes' => Auth::can('time.view')
                ? TimeEntry::sumMinutes(
                    TimeAccess::scope($orgId),
                    TimeService::weekBounds(null)[0] . ' 00:00:00',
                    TimeService::weekBounds(null)[1] . ' 00:00:00',
                    Auth::can('time.view_all') ? null : Auth::id()
                )
                : 0,
            'outstanding' => $finance['outstanding'] ?? null,
            'monthRevenue' => $finance['month_revenue'] ?? null,
            'openInvoiceCount' => (int) ($finance['open_count'] ?? 0),
            'taskCounts' => $taskCounts,
            'agenda' => $agenda,
            'weekStart' => $weekStart->format('Y-m-d'),
            'activity' => ActivityLog::recentForOrganization(
                $orgId,
                8,
                ActivityController::canSeeAllActivity() ? null : Auth::id()
            ),
            'today' => $today,
            'paymentReminders' => ($finance !== null && feature_enabled('finance'))
                ? Invoice::openDueBy($orgId, date('Y-m-d', strtotime($today . ' +14 days')), ProjectAccess::restrictToUserId())
                : [],
            'tasks' => (Auth::can('tasks.view') && feature_enabled('tasks'))
                ? Task::paginate($orgId, 1, 8, null, null, null, null, null, TaskAccess::restrictToUserId())['items']
                : [],
            'projects' => ((Auth::can('projects.view') || Auth::can('projects.view_all')) && feature_enabled('projects'))
                ? Project::paginate($orgId, 1, 8, null, null, null, null, ProjectAccess::restrictToUserId())['items']
                : [],
        ]);
    }

    private function organizationToday(): string
    {
        $organization = $this->currentOrganization();
        $tzName = $organization instanceof \App\Models\Organization ? $organization->timezone : 'UTC';
        try {
            $zone = new \DateTimeZone($tzName !== '' ? $tzName : 'UTC');
        } catch (\Exception) {
            $zone = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $zone))->format('Y-m-d');
    }

    private function clientHome(?\App\Models\Organization $organization, int $orgId, string $today): void
    {
        $clientId = ApprovalService::viewerClientId() ?? -1;
        $this->view('dashboard/client', [
            'title' => 'Dashboard',
            'breadcrumbs' => [['label' => 'Dashboard']],
            'organization' => $organization,
            'clientId' => $clientId,
            'projects' => $clientId > 0 ? Project::forClient($clientId, $orgId) : [],
            'currency' => $organization->currency ?? 'USD',
            'pendingApprovals' => $clientId > 0 ? Approval::countPending($orgId, $clientId, null, 'client') : 0,
            'outstanding' => $clientId > 0 ? Invoice::outstandingForClient($orgId, $clientId) : '0.00',
            'files' => $clientId > 0 ? Deliverable::forClientPortal($orgId, $clientId, true, false, 5) : [],
            'deliverables' => $clientId > 0 ? Deliverable::forClientPortal($orgId, $clientId, false, true, 8) : [],
            'notifications' => Notification::forUser($orgId, (int) Auth::id(), 5),
            'today' => $today,
            'paymentReminders' => $clientId > 0
                ? Invoice::openDueBy($orgId, date('Y-m-d', strtotime($today . ' +14 days')), null, $clientId)
                : [],
        ]);
    }
}
