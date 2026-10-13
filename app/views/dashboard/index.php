<?php
$user = auth_user();
$fullName = trim((string) ($user->name ?? 'there'));
$firstName = preg_split('/\s+/', $fullName)[0] ?: 'there';
$tzName = (string) ($organization->timezone ?? 'UTC');
try {
    $now = new DateTimeImmutable('now', new DateTimeZone($tzName !== '' ? $tzName : 'UTC'));
} catch (Exception) {
    $now = new DateTimeImmutable('now');
}
$hour = (int) $now->format('G');
$greeting = $hour < 5 ? 'Good evening' : ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'));
$taskTotal = array_sum($taskCounts ?? []);
$doneTasks = max(0, $taskTotal - (int) ($openTaskCount ?? 0));
$donePercent = $taskTotal > 0 ? (int) round($doneTasks * 100 / $taskTotal) : 0;
$projectTotal = (int) ($projectCount ?? 0);
$openProjects = (int) ($openProjectCount ?? 0);
$projectPercent = $projectTotal > 0 ? (int) round($openProjects * 100 / $projectTotal) : 0;
$showTasks = can('tasks.view') && feature_enabled('tasks');
$showProjects = (can('projects.view') || can('projects.view_all')) && feature_enabled('projects');
$showClients = can('clients.view') && feature_enabled('clients');
$showFinance = can('finance.view') && feature_enabled('finance');
$showApprovals = (can('approvals.view') || can('approvals.client')) && feature_enabled('approvals');
$weekStartDate = new DateTimeImmutable((string) ($weekStart ?? $now->format('Y-m-d')));
$agendaByDay = [];
foreach ($agenda ?? [] as $item) {
    $key = substr((string) $item->due_date, 0, 10);
    $agendaByDay[$key][] = $item;
}
$selectedDay = $now->format('Y-m-d');
if (!isset($agendaByDay[$selectedDay])) {
    foreach ($agendaByDay as $key => $unused) {
        if ($key >= $selectedDay) {
            $selectedDay = $key;
            break;
        }
    }
}
$projectIcons = [
    'planning' => 'clipboard',
    'active' => 'pencil',
    'on_hold' => 'pause-circle',
    'completed' => 'check-circle',
];
?>

<div class="wf-dash">
    <section class="wf-dash-hero">
        <p class="wf-dash-hello">
            <strong><?= e($greeting) ?>, <?= e($firstName) ?>!</strong>
            <span>Here's what's happening with your work today.</span>
            <span class="wf-dash-hero-meta"><?= e($now->format('D, M j, Y')) ?></span>
            <span class="wf-dash-company"><?= e($organization->name ?? 'Workspace') ?></span>
        </p>
        <div class="wf-dash-hero-brand">
            <img src="<?= e(asset('images/haaye-dash-logo.png')) ?>" alt="Haaye Manager">
        </div>
    </section>

    <div class="wf-dash-metrics">
        <?php if ($showClients): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('clients')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('people') ?></span>
                <span class="wf-dash-metric-label">Clients</span>
                <strong><?= (int) ($clientCount ?? 0) ?></strong>
                <small><?= (int) ($activeClientCount ?? 0) ?> active clients</small>
            </a>
        <?php endif; ?>
        <?php if ($showProjects): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('projects')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('folder') ?></span>
                <span class="wf-dash-metric-label">Projects</span>
                <strong><?= $projectTotal ?></strong>
                <small><?= $openProjects ?> open projects</small>
            </a>
        <?php endif; ?>
        <?php if ($showTasks): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('tasks')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('check2-square') ?></span>
                <span class="wf-dash-metric-label">Tasks</span>
                <strong><?= $taskTotal ?></strong>
                <small><?= (int) ($openTaskCount ?? 0) ?> active tasks</small>
            </a>
        <?php endif; ?>
        <?php if ($showFinance): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('invoices')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('receipt') ?></span>
                <span class="wf-dash-metric-label">Open invoices</span>
                <strong><?= (int) ($openInvoiceCount ?? 0) ?></strong>
                <small>Pending invoices</small>
            </a>
            <a class="wf-dash-metric" href="<?= e(base_url('finance')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('cash-stack') ?></span>
                <span class="wf-dash-metric-label">Total revenue</span>
                <strong><?= e(money($organization->currency ?? 'USD', $monthRevenue ?? 0)) ?></strong>
                <small>This month</small>
            </a>
        <?php endif; ?>
        <?php if ($showApprovals): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('approvals')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('shield-check') ?></span>
                <span class="wf-dash-metric-label">Pending approvals</span>
                <strong><?= (int) ($pendingApprovalCount ?? 0) ?></strong>
                <small>Needs review</small>
            </a>
        <?php endif; ?>
        <?php if (can('pipeline.view') && feature_enabled('pipeline')): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('pipeline')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('layers') ?></span>
                <span class="wf-dash-metric-label">Active pipelines</span>
                <strong><?= (int) ($activePipelineCount ?? 0) ?></strong>
                <small>Workflow runs</small>
            </a>
        <?php endif; ?>
        <?php if ($showTasks): ?>
            <a class="wf-dash-metric" href="<?= e(base_url('my-work')) ?>">
                <span class="wf-dash-metric-icon"><?= icon('exclamation-triangle') ?></span>
                <span class="wf-dash-metric-label">Overdue tasks</span>
                <strong><?= (int) ($overdueTaskCount ?? 0) ?></strong>
                <small>Past due</small>
            </a>
        <?php endif; ?>
    </div>

    <div class="wf-dash-grid">
        <?php if ($showTasks): ?>
            <section class="wf-dash-card">
                <div class="wf-dash-card-head">
                    <div>
                        <h3><?= icon('list-check') ?> Tasks</h3>
                        <p>Your active tasks and deadlines</p>
                    </div>
                    <a href="<?= e(base_url('tasks')) ?>">View all <?= icon('arrow-right') ?></a>
                </div>
                <div class="wf-dash-filters" role="tablist">
                    <button type="button" class="is-on" data-dash-filter="all">All (<?= $taskTotal ?>)</button>
                    <button type="button" data-dash-filter="in_progress">In Progress (<?= (int) ($taskCounts['in_progress'] ?? 0) ?>)</button>
                    <button type="button" data-dash-filter="todo">To Do (<?= (int) ($taskCounts['todo'] ?? 0) ?>)</button>
                    <button type="button" data-dash-filter="done">Completed (<?= $doneTasks ?>)</button>
                </div>
                <?php if (empty($tasks)): ?>
                    <?php \App\Core\View::component('empty-state', [
                        'title' => 'No tasks yet',
                        'message' => 'Tasks you can see will show up here.',
                    ]); ?>
                <?php else: ?>
                    <ul class="wf-dash-tasks">
                        <?php foreach ($tasks as $task): ?>
                            <?php $closed = (int) ($task->status_closed ?? 0) === 1; ?>
                            <li data-status="<?= e($closed ? 'done' : (string) $task->status) ?>">
                                <a href="<?= e(base_url('tasks/' . $task->id)) ?>">
                                    <span class="wf-dash-task-mark<?= $closed ? ' is-done' : '' ?>"><?= icon($closed ? 'check-lg' : 'square') ?></span>
                                    <?php $taskDue = $closed ? null : due_reminder($task->due_date, $today ?? null); ?>
                                    <span class="wf-dash-task-copy">
                                        <strong><?= e($task->title) ?></strong>
                                        <small><?= e($task->project_name ?: 'Project') ?> · <?= e(ucfirst((string) $task->priority)) ?> priority<?php if ($taskDue): ?> · <span class="wf-due is-<?= e($taskDue['tone']) ?>"><?= e($taskDue['label']) ?></span><?php endif; ?></small>
                                    </span>
                                    <span class="wf-dash-task-date"><?= e(format_date($task->due_date)) ?></span>
                                    <?php \App\Core\View::component('status-badge', [
                                        'status' => $task->status,
                                        'label' => $task->status_name ?: null,
                                        'color' => $task->status_color ?: null,
                                    ]); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="wf-dash-filter-empty" hidden>No tasks in this view.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($showProjects): ?>
            <section class="wf-dash-card">
                <div class="wf-dash-card-head">
                    <div>
                        <h3><?= icon('folder2-open') ?> Projects</h3>
                        <p>Project progress and status</p>
                    </div>
                    <a href="<?= e(base_url('projects')) ?>">View all <?= icon('arrow-right') ?></a>
                </div>
                <?php if (empty($projects)): ?>
                    <?php \App\Core\View::component('empty-state', [
                        'title' => 'No projects yet',
                        'message' => 'Projects you can see will show up here.',
                    ]); ?>
                <?php else: ?>
                    <ul class="wf-dash-projects">
                        <?php foreach ($projects as $project): ?>
                            <?php $percent = (int) $project->taskPercent(); ?>
                            <li>
                                <a href="<?= e(base_url('projects/' . $project->id)) ?>">
                                    <span class="wf-dash-project-icon"><?= icon($projectIcons[$project->status] ?? 'folder') ?></span>
                                    <span class="wf-dash-project-copy">
                                        <strong><?= e($project->name) ?></strong>
                                        <small><?= e($project->client_name ?: 'Client') ?> · <?= $percent ?>%</small>
                                        <span class="wf-dash-bar"><span style="width: <?= $percent ?>%"></span></span>
                                    </span>
                                    <?php \App\Core\View::component('status-badge', [
                                        'status' => $project->status,
                                        'label' => $project->status_name ?: null,
                                        'color' => $project->status_color ?: null,
                                    ]); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($showFinance && !empty($paymentReminders)): ?>
        <section class="wf-dash-card wf-dash-reminders">
            <div class="wf-dash-card-head">
                <div>
                    <h3><?= icon('bell') ?> Payment reminders</h3>
                    <p>Invoices that are overdue or coming due</p>
                </div>
                <a href="<?= e(base_url('invoices')) ?>">View invoices <?= icon('arrow-right') ?></a>
            </div>
            <ul class="wf-dash-tasks">
                <?php foreach ($paymentReminders as $invoice): ?>
                    <?php $payDue = due_reminder($invoice->due_date, $today ?? null); ?>
                    <li>
                        <a href="<?= e(base_url('invoices/' . $invoice->id)) ?>">
                            <span class="wf-dash-project-icon"><?= icon('receipt') ?></span>
                            <span class="wf-dash-task-copy">
                                <strong><?= e($invoice->number) ?></strong>
                                <small><?= e($invoice->client_name ?: 'Client') ?><?php if ($payDue): ?> · <span class="wf-due is-<?= e($payDue['tone']) ?>"><?= e($payDue['label']) ?></span><?php endif; ?></small>
                            </span>
                            <span class="wf-dash-task-date"><?= e(money($invoice->currency ?: ($organization->currency ?? 'USD'), $invoice->balance)) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="wf-dash-lower">
        <?php if ($showTasks): ?>
            <section class="wf-dash-card">
                <div class="wf-dash-card-head">
                    <div>
                        <h3><?= icon('calendar3') ?> Calendar</h3>
                    </div>
                    <a href="<?= e(base_url('calendar')) ?>">View calendar <?= icon('arrow-right') ?></a>
                </div>
                <div class="wf-dash-week">
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <?php $day = $weekStartDate->modify('+' . $i . ' days'); $key = $day->format('Y-m-d'); ?>
                        <button type="button" class="<?= $key === $selectedDay ? 'is-on' : '' ?>" data-dash-day="<?= e($key) ?>">
                            <span><?= e($day->format('D')) ?></span>
                            <strong><?= e($day->format('j')) ?></strong>
                        </button>
                    <?php endfor; ?>
                </div>
                <ul class="wf-dash-agenda">
                    <?php foreach ($agenda ?? [] as $item): ?>
                        <?php $agendaDue = (int) ($item->status_closed ?? 0) === 1 ? null : due_reminder($item->due_date, $today ?? null); ?>
                        <li data-day="<?= e(substr((string) $item->due_date, 0, 10)) ?>" <?= substr((string) $item->due_date, 0, 10) === $selectedDay ? '' : 'hidden' ?>>
                            <a href="<?= e(base_url('tasks/' . $item->id)) ?>">
                                <span><?= e(format_date($item->due_date)) ?></span>
                                <strong><?= e($item->title) ?></strong>
                                <small><?= e($item->project_name ?: 'Project') ?><?php if ($agendaDue): ?> · <span class="wf-due is-<?= e($agendaDue['tone']) ?>"><?= e($agendaDue['label']) ?></span><?php endif; ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="wf-dash-agenda-empty" <?= isset($agendaByDay[$selectedDay]) ? 'hidden' : '' ?>>Nothing due this day.</p>
            </section>
        <?php endif; ?>

        <section class="wf-dash-card">
            <div class="wf-dash-card-head">
                <div>
                    <h3><?= icon('bar-chart') ?> Quick overview</h3>
                    <p>At a glance</p>
                </div>
            </div>
            <div class="wf-dash-overview">
                <?php if ($showTasks): ?>
                    <div>
                        <span class="wf-dash-ring" style="--pct: <?= $donePercent ?>"><?= $donePercent ?>%</span>
                        <strong>Tasks completed</strong>
                        <small><?= $doneTasks ?> / <?= $taskTotal ?></small>
                    </div>
                <?php endif; ?>
                <?php if ($showProjects): ?>
                    <div>
                        <span class="wf-dash-ring" style="--pct: <?= $projectPercent ?>"><?= $projectPercent ?>%</span>
                        <strong>Open projects</strong>
                        <small><?= $openProjects ?> / <?= $projectTotal ?></small>
                    </div>
                <?php endif; ?>
                <?php if ($showApprovals): ?>
                    <a href="<?= e(base_url('approvals')) ?>">
                        <span class="wf-dash-ring is-count"><?= (int) ($pendingApprovalCount ?? 0) ?></span>
                        <strong>Pending approvals</strong>
                        <small>Needs review</small>
                    </a>
                <?php endif; ?>
                <?php if ($showFinance): ?>
                    <a href="<?= e(base_url('finance')) ?>">
                        <span class="wf-dash-ring is-money"><?= icon('cash') ?></span>
                        <strong>Monthly revenue</strong>
                        <small><?= e(money($organization->currency ?? 'USD', $monthRevenue ?? 0)) ?></small>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <section class="wf-dash-card">
            <div class="wf-dash-card-head">
                <div>
                    <h3><?= icon('lightning-charge') ?> Recent activity</h3>
                    <p>Who updated what in this workspace</p>
                </div>
                <?php if (can('activity.view')): ?>
                    <a href="<?= e(base_url('activity')) ?>">All activity</a>
                <?php endif; ?>
            </div>
            <?php if (empty($activity)): ?>
                <p class="wf-dash-quiet">No recent activity yet.</p>
            <?php else: ?>
                <ul class="wf-dash-activity">
                    <?php foreach ($activity as $item): ?>
                        <li>
                            <?php if (can('activity.view')): ?>
                            <a href="<?= e(base_url('activity')) ?>">
                            <?php else: ?>
                            <div>
                            <?php endif; ?>
                                <span class="wf-dash-avatar"><?= e(user_initials($item->actor_name ?: 'System')) ?></span>
                                <span>
                                    <strong><?= e($item->actor_name ?: 'System') ?></strong>
                                    <small><?= e($item->description) ?></small>
                                    <small><?= e(format_datetime($item->created_at)) ?></small>
                                </span>
                            <?php if (can('activity.view')): ?></a><?php else: ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<script>
document.querySelectorAll('[data-dash-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
        var filter = button.getAttribute('data-dash-filter');
        var card = button.closest('.wf-dash-card');
        card.querySelectorAll('[data-dash-filter]').forEach(function (item) { item.classList.remove('is-on'); });
        button.classList.add('is-on');
        var shown = 0;
        card.querySelectorAll('.wf-dash-tasks li').forEach(function (row) {
            var match = filter === 'all' || row.getAttribute('data-status') === filter;
            row.hidden = !match;
            if (match) shown += 1;
        });
        var empty = card.querySelector('.wf-dash-filter-empty');
        if (empty) empty.hidden = shown > 0;
    });
});
document.querySelectorAll('[data-dash-day]').forEach(function (button) {
    button.addEventListener('click', function () {
        var day = button.getAttribute('data-dash-day');
        var card = button.closest('.wf-dash-card');
        card.querySelectorAll('[data-dash-day]').forEach(function (item) { item.classList.remove('is-on'); });
        button.classList.add('is-on');
        var shown = 0;
        card.querySelectorAll('.wf-dash-agenda li').forEach(function (row) {
            var match = row.getAttribute('data-day') === day;
            row.hidden = !match;
            if (match) shown += 1;
        });
        var empty = card.querySelector('.wf-dash-agenda-empty');
        if (empty) empty.hidden = shown > 0;
    });
});
</script>
