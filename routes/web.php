<?php

declare(strict_types=1);

use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\ClientContactController;
use App\Controllers\ClientController;
use App\Controllers\DashboardController;
use App\Controllers\InstallController;
use App\Controllers\OrganizationController;
use App\Controllers\MilestoneController;
use App\Controllers\PortalController;
use App\Controllers\ProfileController;
use App\Controllers\ProjectController;
use App\Controllers\ProjectMemberController;
use App\Controllers\ProjectStatusController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
use App\Controllers\TaskAttachmentController;
use App\Controllers\TaskCommentController;
use App\Controllers\TaskController;
use App\Controllers\TaskStatusController;
use App\Controllers\UserController;
use App\Controllers\ApprovalController;
use App\Controllers\DeliverableController;
use App\Controllers\DeliverableStatusController;
use App\Controllers\ExpenseController;
use App\Controllers\FinanceController;
use App\Controllers\GuideController;
use App\Controllers\InvoiceController;
use App\Controllers\TimeController;
use App\Controllers\BookingController;
use App\Controllers\InventoryController;
use App\Controllers\CalendarController;
use App\Controllers\LeadController;
use App\Controllers\CrmWorkflowController;
use App\Controllers\ContentGridController;
use App\Controllers\NotificationController;
use App\Controllers\WorkflowController;
use App\Controllers\WorkspaceController;
use App\Controllers\PipelineController;
use App\Controllers\MyWorkController;
use App\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Controllers\Platform\OrganizationController as PlatformOrganizationController;
use App\Controllers\Platform\PlanController as PlatformPlanController;
use App\Controllers\Platform\PlanRequestController as PlatformPlanRequestController;
use App\Controllers\Platform\UserController as PlatformUserController;
use App\Core\Router;

$router = new Router();

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'store'], ['csrf']);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);
$router->post('/workspace/switch', [AuthController::class, 'switchWorkspace'], ['auth', 'csrf']);
$router->get('/workspaces/create', [WorkspaceController::class, 'create'], ['auth']);
$router->post('/workspaces', [WorkspaceController::class, 'store'], ['auth', 'csrf']);
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'forgot'], ['guest', 'csrf']);
$router->get('/reset-password', [AuthController::class, 'showReset'], ['guest']);
$router->post('/reset-password', [AuthController::class, 'reset'], ['guest', 'csrf']);

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/activity', [ActivityController::class, 'index'], ['auth', 'can:activity.view']);
$router->get('/notifications/{id}', [NotificationController::class, 'open'], ['auth']);
$router->post('/notifications/read', [NotificationController::class, 'readAll'], ['auth', 'csrf']);
$router->get('/guide', [GuideController::class, 'index'], ['auth']);

$router->get('/portal/company', [PortalController::class, 'company'], ['auth', 'can:portal.view']);
$router->get('/portal/projects', [PortalController::class, 'projects'], ['auth', 'can:portal.view']);
$router->get('/portal/projects/{id}', [PortalController::class, 'project'], ['auth', 'can:portal.view']);
$router->get('/portal/invoices', [PortalController::class, 'invoices'], ['auth', 'can:portal.view']);
$router->get('/portal/invoices/{id}', [PortalController::class, 'invoice'], ['auth', 'can:portal.view']);
$router->get('/portal/payments', [PortalController::class, 'payments'], ['auth', 'can:portal.view']);
$router->get('/portal/files', [PortalController::class, 'files'], ['auth', 'can:portal.view']);
$router->get('/portal/notifications', [PortalController::class, 'notifications'], ['auth', 'can:portal.view']);
$router->post('/portal/notifications/read', [PortalController::class, 'readAll'], ['auth', 'csrf', 'can:portal.view']);
$router->get('/portal/notifications/{id}', [PortalController::class, 'openNotification'], ['auth', 'can:portal.view']);

$router->get('/profile', [ProfileController::class, 'edit'], ['auth', 'can:profile.view']);
$router->post('/profile', [ProfileController::class, 'update'], ['auth', 'csrf', 'can:profile.update']);

$router->get('/settings', [SettingsController::class, 'index'], ['auth']);
$router->get('/settings/appearance', [SettingsController::class, 'appearance'], ['auth']);
$router->get('/settings/organization', [OrganizationController::class, 'settings'], ['auth', 'can:organization.view']);
$router->get('/settings/organization/plan', [OrganizationController::class, 'plan'], ['auth', 'can:organization.update']);
$router->post('/settings/organization/plan', [OrganizationController::class, 'submitPlan'], ['auth', 'csrf', 'can:organization.update']);
$router->post('/settings/organization', [OrganizationController::class, 'updateSettings'], ['auth', 'csrf', 'can:organization.update']);

$router->get('/users', [UserController::class, 'index'], ['auth', 'can:users.view']);
$router->get('/users/create', [UserController::class, 'create'], ['auth', 'can:users.create']);
$router->post('/users', [UserController::class, 'store'], ['auth', 'csrf', 'can:users.create']);
$router->get('/users/{id}', [UserController::class, 'show'], ['auth', 'can:users.view']);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'can:users.update']);
$router->post('/users/{id}', [UserController::class, 'update'], ['auth', 'csrf', 'can:users.update']);
$router->post('/users/{id}/activate', [UserController::class, 'activate'], ['auth', 'csrf', 'can:users.activate']);
$router->post('/users/{id}/deactivate', [UserController::class, 'deactivate'], ['auth', 'csrf', 'can:users.activate']);

$router->get('/leads', [LeadController::class, 'index'], ['auth', 'can:leads.view']);
$router->get('/leads/create', [LeadController::class, 'create'], ['auth', 'can:leads.create']);
$router->post('/leads', [LeadController::class, 'store'], ['auth', 'csrf', 'can:leads.create']);
$router->get('/leads/{id}', [LeadController::class, 'show'], ['auth', 'can:leads.view']);
$router->get('/leads/{id}/edit', [LeadController::class, 'edit'], ['auth', 'can:leads.update']);
$router->post('/leads/{id}/stage', [LeadController::class, 'stage'], ['auth', 'csrf', 'can:leads.update']);
$router->post('/leads/{id}', [LeadController::class, 'update'], ['auth', 'csrf', 'can:leads.update']);
$router->post('/leads/{id}/convert', [LeadController::class, 'convert'], ['auth', 'csrf', 'can:leads.convert']);
$router->post('/leads/{id}/delete', [LeadController::class, 'destroy'], ['auth', 'csrf', 'can:leads.update']);

$router->get('/work-flow', [CrmWorkflowController::class, 'index'], ['auth', 'can:work_flow.view']);
$router->post('/work-flow/roles', [CrmWorkflowController::class, 'storeRole'], ['auth', 'csrf', 'can:work_flow.manage']);
$router->post('/work-flow/roles/{id}', [CrmWorkflowController::class, 'updateRole'], ['auth', 'csrf', 'can:work_flow.manage']);
$router->post('/work-flow/roles/{id}/delete', [CrmWorkflowController::class, 'destroyRole'], ['auth', 'csrf', 'can:work_flow.manage']);
$router->post('/work-flow/tasks', [CrmWorkflowController::class, 'storeTask'], ['auth', 'csrf', 'can:work_flow.assign']);
$router->post('/work-flow/tasks/{id}', [CrmWorkflowController::class, 'updateTask'], ['auth', 'csrf']);
$router->post('/work-flow/tasks/{id}/status', [CrmWorkflowController::class, 'status'], ['auth', 'csrf']);
$router->post('/work-flow/tasks/{id}/delete', [CrmWorkflowController::class, 'destroyTask'], ['auth', 'csrf', 'can:work_flow.manage']);

$router->get('/work-flow/content-grids', [ContentGridController::class, 'index'], ['auth', 'can:work_flow.view']);
$router->post('/work-flow/content-grids', [ContentGridController::class, 'store'], ['auth', 'csrf', 'can:work_flow.assign']);
$router->get('/work-flow/content-grids/{id}', [ContentGridController::class, 'show'], ['auth', 'can:work_flow.view']);
$router->post('/work-flow/content-grids/{id}', [ContentGridController::class, 'update'], ['auth', 'csrf', 'can:work_flow.assign']);
$router->post('/work-flow/content-grids/{id}/delete', [ContentGridController::class, 'destroy'], ['auth', 'csrf', 'can:work_flow.manage']);

$router->get('/bookings', [BookingController::class, 'index'], ['auth', 'can:bookings.view']);
$router->get('/bookings/create', [BookingController::class, 'create'], ['auth', 'can:bookings.create']);
$router->post('/bookings', [BookingController::class, 'store'], ['auth', 'csrf', 'can:bookings.create']);
$router->get('/bookings/{id}', [BookingController::class, 'show'], ['auth', 'can:bookings.view']);
$router->get('/bookings/{id}/edit', [BookingController::class, 'edit'], ['auth', 'can:bookings.update']);
$router->post('/bookings/{id}', [BookingController::class, 'update'], ['auth', 'csrf', 'can:bookings.update']);
$router->post('/bookings/{id}/status', [BookingController::class, 'status'], ['auth', 'csrf', 'can:bookings.update']);
$router->post('/bookings/{id}/submit', [BookingController::class, 'submit'], ['auth', 'csrf', 'can:bookings.view']);
$router->post('/bookings/{id}/review', [BookingController::class, 'review'], ['auth', 'csrf', 'can:bookings.view']);
$router->post('/bookings/{id}/delete', [BookingController::class, 'destroy'], ['auth', 'csrf', 'can:bookings.update']);
$router->post('/inventory/items', [InventoryController::class, 'store'], ['auth', 'csrf']);

$router->get('/clients', [ClientController::class, 'index'], ['auth', 'can:clients.view']);
$router->get('/clients/create', [ClientController::class, 'create'], ['auth', 'can:clients.create']);
$router->post('/clients', [ClientController::class, 'store'], ['auth', 'csrf', 'can:clients.create']);
$router->get('/clients/{id}', [ClientController::class, 'show'], ['auth', 'can:clients.view']);
$router->get('/clients/{id}/edit', [ClientController::class, 'edit'], ['auth', 'can:clients.update']);
$router->post('/clients/{id}', [ClientController::class, 'update'], ['auth', 'csrf', 'can:clients.update']);
$router->post('/clients/{id}/activate', [ClientController::class, 'activate'], ['auth', 'csrf', 'can:clients.activate']);
$router->post('/clients/{id}/archive', [ClientController::class, 'archive'], ['auth', 'csrf', 'can:clients.activate']);
$router->post('/clients/{id}/contacts', [ClientContactController::class, 'store'], ['auth', 'csrf', 'can:clients.contacts.manage']);
$router->get('/clients/{id}/contacts/{contactId}/edit', [ClientContactController::class, 'edit'], ['auth', 'can:clients.contacts.manage']);
$router->post('/clients/{id}/contacts/{contactId}', [ClientContactController::class, 'update'], ['auth', 'csrf', 'can:clients.contacts.manage']);
$router->post('/clients/{id}/contacts/{contactId}/delete', [ClientContactController::class, 'destroy'], ['auth', 'csrf', 'can:clients.contacts.manage']);
$router->post('/clients/{id}/contacts/{contactId}/primary', [ClientContactController::class, 'primary'], ['auth', 'csrf', 'can:clients.contacts.manage']);

$router->get('/projects', [ProjectController::class, 'index'], ['auth', 'can:projects.view']);
$router->get('/projects/create', [ProjectController::class, 'create'], ['auth', 'can:projects.create']);
$router->post('/projects', [ProjectController::class, 'store'], ['auth', 'csrf', 'can:projects.create']);
$router->get('/projects/statuses', [ProjectStatusController::class, 'index'], ['auth', 'can:projects.statuses.manage']);
$router->post('/projects/statuses', [ProjectStatusController::class, 'store'], ['auth', 'csrf', 'can:projects.statuses.manage']);
$router->post('/projects/statuses/{id}', [ProjectStatusController::class, 'update'], ['auth', 'csrf', 'can:projects.statuses.manage']);
$router->post('/projects/statuses/{id}/delete', [ProjectStatusController::class, 'destroy'], ['auth', 'csrf', 'can:projects.statuses.manage']);
$router->get('/projects/{id}', [ProjectController::class, 'show'], ['auth', 'can:projects.view']);
$router->get('/projects/{id}/edit', [ProjectController::class, 'edit'], ['auth', 'can:projects.update']);
$router->post('/projects/{id}', [ProjectController::class, 'update'], ['auth', 'csrf', 'can:projects.update']);
$router->post('/projects/{id}/members', [ProjectMemberController::class, 'store'], ['auth', 'csrf', 'can:projects.members.manage']);
$router->post('/projects/{id}/members/{memberId}/role', [ProjectMemberController::class, 'update'], ['auth', 'csrf', 'can:projects.members.manage']);
$router->post('/projects/{id}/members/{memberId}/remove', [ProjectMemberController::class, 'destroy'], ['auth', 'csrf', 'can:projects.members.manage']);
$router->post('/projects/{id}/milestones', [MilestoneController::class, 'store'], ['auth', 'csrf', 'can:projects.milestones.manage']);
$router->get('/projects/{id}/milestones/{milestoneId}/edit', [MilestoneController::class, 'edit'], ['auth', 'can:projects.milestones.manage']);
$router->post('/projects/{id}/milestones/{milestoneId}', [MilestoneController::class, 'update'], ['auth', 'csrf', 'can:projects.milestones.manage']);
$router->post('/projects/{id}/milestones/{milestoneId}/delete', [MilestoneController::class, 'destroy'], ['auth', 'csrf', 'can:projects.milestones.manage']);

$router->get('/my-work', [MyWorkController::class, 'index'], ['auth']);

$router->get('/pipeline', [PipelineController::class, 'index'], ['auth', 'can:pipeline.view']);
$router->get('/pipeline/templates/create', [PipelineController::class, 'createTemplate'], ['auth', 'can:pipeline.templates']);
$router->post('/pipeline/templates', [PipelineController::class, 'storeTemplate'], ['auth', 'csrf', 'can:pipeline.templates']);
$router->get('/pipeline/templates/{id}', [PipelineController::class, 'showTemplate'], ['auth', 'can:pipeline.view']);
$router->post('/pipeline/templates/{id}', [PipelineController::class, 'updateTemplate'], ['auth', 'csrf', 'can:pipeline.templates']);
$router->post('/pipeline/templates/{id}/stages', [PipelineController::class, 'storeStage'], ['auth', 'csrf', 'can:pipeline.templates']);
$router->post('/pipeline/templates/{id}/stages/{stageId}', [PipelineController::class, 'updateStage'], ['auth', 'csrf', 'can:pipeline.templates']);
$router->post('/pipeline/templates/{id}/stages/{stageId}/delete', [PipelineController::class, 'destroyStage'], ['auth', 'csrf', 'can:pipeline.templates']);
$router->post('/pipeline/runs', [PipelineController::class, 'startRun'], ['auth', 'csrf', 'can:pipeline.manage']);
$router->get('/pipeline/runs/{id}', [PipelineController::class, 'showRun'], ['auth', 'can:pipeline.view']);
$router->post('/pipeline/runs/{id}/confirm-next', [PipelineController::class, 'confirmNext'], ['auth', 'csrf', 'can:pipeline.manage']);
$router->post('/pipeline/runs/{id}/pause', [PipelineController::class, 'pause'], ['auth', 'csrf', 'can:pipeline.manage']);
$router->post('/pipeline/runs/{id}/resume', [PipelineController::class, 'resume'], ['auth', 'csrf', 'can:pipeline.manage']);
$router->post('/pipeline/runs/{id}/cancel', [PipelineController::class, 'cancel'], ['auth', 'csrf', 'can:pipeline.manage']);
$router->post('/pipeline/runs/{id}/stages/{stageId}/reassign', [PipelineController::class, 'reassignStage'], ['auth', 'csrf', 'can:pipeline.manage']);

$router->get('/tasks', [TaskController::class, 'index'], ['auth', 'can:tasks.view']);
$router->get('/tasks/mine', [TaskController::class, 'mine'], ['auth', 'can:tasks.view']);
$router->get('/tasks/create', [TaskController::class, 'create'], ['auth', 'can:tasks.create']);
$router->post('/tasks', [TaskController::class, 'store'], ['auth', 'csrf', 'can:tasks.create']);
$router->get('/tasks/statuses', [TaskStatusController::class, 'index'], ['auth', 'can:tasks.statuses.manage']);
$router->post('/tasks/statuses', [TaskStatusController::class, 'store'], ['auth', 'csrf', 'can:tasks.statuses.manage']);
$router->post('/tasks/statuses/{id}', [TaskStatusController::class, 'update'], ['auth', 'csrf', 'can:tasks.statuses.manage']);
$router->post('/tasks/statuses/{id}/delete', [TaskStatusController::class, 'destroy'], ['auth', 'csrf', 'can:tasks.statuses.manage']);
$router->get('/tasks/{id}', [TaskController::class, 'show'], ['auth', 'can:tasks.view']);
$router->get('/tasks/{id}/edit', [TaskController::class, 'edit'], ['auth', 'can:tasks.update']);
$router->post('/tasks/{id}', [TaskController::class, 'update'], ['auth', 'csrf', 'can:tasks.update']);
$router->post('/tasks/{id}/start', [TaskController::class, 'start'], ['auth', 'csrf', 'can:tasks.view']);
$router->post('/tasks/{id}/submit', [TaskController::class, 'submit'], ['auth', 'csrf', 'can:tasks.view']);
$router->post('/tasks/{id}/status', [TaskController::class, 'move'], ['auth', 'csrf', 'can:tasks.view']);
$router->post('/tasks/{id}/delete', [TaskController::class, 'destroy'], ['auth', 'csrf', 'can:tasks.update']);
$router->post('/tasks/{id}/comments', [TaskCommentController::class, 'store'], ['auth', 'csrf', 'can:tasks.comment']);
$router->post('/tasks/{id}/comments/{commentId}/delete', [TaskCommentController::class, 'destroy'], ['auth', 'csrf', 'can:tasks.view']);
$router->post('/tasks/{id}/attachments', [TaskAttachmentController::class, 'store'], ['auth', 'csrf', 'can:tasks.attachments']);
$router->get('/tasks/{id}/attachments/{attachmentId}', [TaskAttachmentController::class, 'download'], ['auth', 'can:tasks.view']);
$router->post('/tasks/{id}/attachments/{attachmentId}/delete', [TaskAttachmentController::class, 'destroy'], ['auth', 'csrf', 'can:tasks.view']);

$router->get('/deliverables', [DeliverableController::class, 'index'], ['auth', 'can:deliverables.view']);
$router->get('/deliverables/create', [DeliverableController::class, 'create'], ['auth', 'can:deliverables.create']);
$router->post('/deliverables', [DeliverableController::class, 'store'], ['auth', 'csrf', 'can:deliverables.create']);
$router->get('/deliverables/statuses', [DeliverableStatusController::class, 'index'], ['auth', 'can:deliverables.statuses.manage']);
$router->post('/deliverables/statuses', [DeliverableStatusController::class, 'store'], ['auth', 'csrf', 'can:deliverables.statuses.manage']);
$router->post('/deliverables/statuses/{id}', [DeliverableStatusController::class, 'update'], ['auth', 'csrf', 'can:deliverables.statuses.manage']);
$router->post('/deliverables/statuses/{id}/delete', [DeliverableStatusController::class, 'destroy'], ['auth', 'csrf', 'can:deliverables.statuses.manage']);
$router->get('/deliverables/{id}', [DeliverableController::class, 'show'], ['auth', 'can:deliverables.view']);
$router->get('/deliverables/{id}/edit', [DeliverableController::class, 'edit'], ['auth', 'can:deliverables.update']);
$router->post('/deliverables/{id}', [DeliverableController::class, 'update'], ['auth', 'csrf', 'can:deliverables.update']);
$router->post('/deliverables/{id}/status', [DeliverableController::class, 'move'], ['auth', 'csrf', 'can:deliverables.update']);
$router->post('/deliverables/{id}/file', [DeliverableController::class, 'upload'], ['auth', 'csrf', 'can:deliverables.update']);
$router->get('/deliverables/{id}/file', [DeliverableController::class, 'download'], ['auth', 'can:deliverables.view']);
$router->post('/deliverables/{id}/delete', [DeliverableController::class, 'destroy'], ['auth', 'csrf', 'can:deliverables.update']);

$router->get('/approvals', [ApprovalController::class, 'index'], ['auth', 'can:approvals.view']);
$router->get('/approvals/{id}', [ApprovalController::class, 'show'], ['auth', 'can:approvals.view']);
$router->post('/approvals/{id}/decide', [ApprovalController::class, 'decide'], ['auth', 'csrf', 'can:approvals.view']);

$router->get('/workflows', [WorkflowController::class, 'index'], ['auth', 'can:workflows.view']);
$router->post('/workflows', [WorkflowController::class, 'store'], ['auth', 'csrf', 'can:workflows.manage']);
$router->post('/workflows/{id}/delete', [WorkflowController::class, 'destroy'], ['auth', 'csrf', 'can:workflows.manage']);

$router->get('/finance', [FinanceController::class, 'index'], ['auth', 'can:finance.view']);

$router->get('/invoices', [InvoiceController::class, 'index'], ['auth', 'can:invoices.view']);
$router->get('/invoices/create', [InvoiceController::class, 'create'], ['auth', 'can:invoices.manage']);
$router->post('/invoices', [InvoiceController::class, 'store'], ['auth', 'csrf', 'can:invoices.manage']);
$router->get('/invoices/{id}', [InvoiceController::class, 'show'], ['auth', 'can:invoices.view']);
$router->get('/invoices/{id}/edit', [InvoiceController::class, 'edit'], ['auth', 'can:invoices.manage']);
$router->post('/invoices/{id}', [InvoiceController::class, 'update'], ['auth', 'csrf', 'can:invoices.manage']);
$router->post('/invoices/{id}/send', [InvoiceController::class, 'send'], ['auth', 'csrf', 'can:invoices.manage']);
$router->post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'], ['auth', 'csrf', 'can:invoices.manage']);
$router->post('/invoices/{id}/payments', [InvoiceController::class, 'pay'], ['auth', 'csrf', 'can:payments.manage']);
$router->post('/payments/{id}/delete', [InvoiceController::class, 'destroyPayment'], ['auth', 'csrf', 'can:payments.manage']);

$router->get('/expenses', [ExpenseController::class, 'index'], ['auth', 'can:expenses.view']);
$router->get('/expenses/create', [ExpenseController::class, 'create'], ['auth', 'can:expenses.create']);
$router->post('/expenses', [ExpenseController::class, 'store'], ['auth', 'csrf', 'can:expenses.create']);
$router->get('/expenses/{id}', [ExpenseController::class, 'show'], ['auth', 'can:expenses.view']);
$router->post('/expenses/{id}/decide', [ExpenseController::class, 'decide'], ['auth', 'csrf', 'can:expenses.approve']);
$router->get('/expenses/{id}/receipt', [ExpenseController::class, 'download'], ['auth', 'can:expenses.view']);
$router->post('/expenses/{id}/delete', [ExpenseController::class, 'destroy'], ['auth', 'csrf', 'can:expenses.view']);

$router->get('/time', [TimeController::class, 'index'], ['auth', 'can:time.view']);
$router->get('/calendar', [CalendarController::class, 'index'], ['auth', 'can:calendar.view']);
$router->get('/time/log', [TimeController::class, 'create'], ['auth', 'can:time.create']);
$router->post('/time', [TimeController::class, 'store'], ['auth', 'csrf', 'can:time.create']);
$router->post('/time/start', [TimeController::class, 'start'], ['auth', 'csrf', 'can:time.create']);
$router->post('/time/stop', [TimeController::class, 'stop'], ['auth', 'csrf', 'can:time.update']);
$router->get('/time/workload', [TimeController::class, 'workload'], ['auth', 'can:time.view_all']);
$router->get('/time/report', [TimeController::class, 'report'], ['auth', 'can:time.view']);
$router->get('/time/{id}/edit', [TimeController::class, 'edit'], ['auth', 'can:time.view']);
$router->post('/time/{id}', [TimeController::class, 'update'], ['auth', 'csrf', 'can:time.view']);
$router->post('/time/{id}/delete', [TimeController::class, 'destroy'], ['auth', 'csrf', 'can:time.view']);

$router->get('/roles', [RoleController::class, 'index'], ['auth', 'can:roles.view']);
$router->get('/roles/{id}', [RoleController::class, 'show'], ['auth', 'can:roles.view']);
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], ['auth', 'can:roles.view']);
$router->post('/roles/{id}', [RoleController::class, 'update'], ['auth', 'csrf', 'can:roles.view']);

$router->get('/platform', [PlatformDashboardController::class, 'index'], ['auth', 'platform']);
$router->get('/platform/guide', [GuideController::class, 'platform'], ['auth', 'platform']);
$router->get('/platform/plan-requests', [PlatformPlanRequestController::class, 'index'], ['auth', 'platform', 'can:platform.plans.view']);
$router->get('/platform/plan-requests/{id}', [PlatformPlanRequestController::class, 'show'], ['auth', 'platform', 'can:platform.plans.view']);
$router->post('/platform/plan-requests/{id}/approve', [PlatformPlanRequestController::class, 'approve'], ['auth', 'platform', 'csrf', 'can:platform.plans.update']);
$router->post('/platform/plan-requests/{id}/reject', [PlatformPlanRequestController::class, 'reject'], ['auth', 'platform', 'csrf', 'can:platform.plans.update']);
$router->get('/platform/plans', [PlatformPlanController::class, 'index'], ['auth', 'platform', 'can:platform.plans.view']);
$router->post('/platform/plans', [PlatformPlanController::class, 'update'], ['auth', 'platform', 'csrf', 'can:platform.plans.update']);
$router->post('/platform/plans/create', [PlatformPlanController::class, 'store'], ['auth', 'platform', 'csrf', 'can:platform.plans.update']);
$router->post('/platform/plans/delete', [PlatformPlanController::class, 'destroy'], ['auth', 'platform', 'csrf', 'can:platform.plans.update']);
$router->get('/platform/organizations', [PlatformOrganizationController::class, 'index'], ['auth', 'platform', 'can:platform.organizations.view']);
$router->get('/platform/organizations/create', [PlatformOrganizationController::class, 'create'], ['auth', 'platform', 'can:platform.organizations.create']);
$router->post('/platform/organizations', [PlatformOrganizationController::class, 'store'], ['auth', 'platform', 'csrf', 'can:platform.organizations.create']);
$router->get('/platform/organizations/{id}', [PlatformOrganizationController::class, 'show'], ['auth', 'platform', 'can:platform.organizations.view']);
$router->get('/platform/organizations/{id}/edit', [PlatformOrganizationController::class, 'edit'], ['auth', 'platform', 'can:platform.organizations.update']);
$router->post('/platform/organizations/{id}', [PlatformOrganizationController::class, 'update'], ['auth', 'platform', 'csrf', 'can:platform.organizations.update']);

$router->get('/platform/users', [PlatformUserController::class, 'index'], ['auth', 'platform', 'can:platform.users.view']);
$router->get('/platform/users/create', [PlatformUserController::class, 'create'], ['auth', 'platform', 'can:platform.users.create']);
$router->post('/platform/users', [PlatformUserController::class, 'store'], ['auth', 'platform', 'csrf', 'can:platform.users.create']);
$router->get('/platform/users/{id}/edit', [PlatformUserController::class, 'edit'], ['auth', 'platform', 'can:platform.users.update']);
$router->post('/platform/users/{id}', [PlatformUserController::class, 'update'], ['auth', 'platform', 'csrf', 'can:platform.users.update']);

return $router;
