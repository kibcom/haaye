<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Employee workspace</p>
        <h2>My Work</h2>
        <p class="text-muted mb-0">Your assigned tasks — start, deliver, submit. Approval is separate.</p>
    </div>
</div>

<div class="row g-2 mb-3">
    <?php
    $tiles = [
        ['Mine', $counts['mine'] ?? 0, 'tasks/mine'],
        ['Due today', $counts['due_today'] ?? 0, null],
        ['Overdue', $counts['overdue'] ?? 0, null],
        ['In progress', $counts['in_progress'] ?? 0, null],
        ['Changes requested', $counts['changes_requested'] ?? 0, null],
        ['Submitted', $counts['submitted'] ?? 0, null],
        ['Approved', $counts['approved'] ?? 0, null],
        ['Upcoming', $counts['upcoming'] ?? 0, null],
    ];
    foreach ($tiles as [$label, $n, $href]):
    ?>
        <div class="col-6 col-md-3">
            <?php if ($href): ?>
                <a class="wf-stat d-block text-decoration-none" href="<?= e(base_url($href)) ?>">
                    <span><?= e($label) ?></span>
                    <strong><?= (int) $n ?></strong>
                </a>
            <?php else: ?>
                <div class="wf-stat">
                    <span><?= e($label) ?></span>
                    <strong><?= (int) $n ?></strong>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
$sections = [
    ['Overdue', $overdue ?? [], 'danger'],
    ['Due today', $dueToday ?? [], 'warning'],
    ['Changes requested', $changesRequested ?? [], 'warning'],
    ['In progress', $inProgress ?? [], 'info'],
    ['Submitted for approval', $submitted ?? [], 'primary'],
    ['Upcoming', $upcoming ?? [], 'secondary'],
];
?>

<?php foreach ($sections as [$title, $tasks, $tone]): ?>
    <section class="wf-card mb-3">
        <div class="wf-card-head"><h3><?= e($title) ?></h3></div>
        <?php if ($tasks === []): ?>
            <p class="text-muted small mb-0">None.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table align-middle mb-0">
                    <thead>
                    <tr><th>Task</th><th>Project</th><th>Priority</th><th>Due</th><th>Status</th><th>Next</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $status = (string) $task->status;
                        $next = match (true) {
                            \App\Services\TaskLifecycle::canStart($status) => 'Start work',
                            \App\Services\TaskLifecycle::canSubmit($status) => 'Submit for approval',
                            \App\Services\TaskLifecycle::canApprove($status) => 'Awaiting approval',
                            $status === 'changes_requested' => 'Revise & resubmit',
                            $status === 'approved' || $status === 'completed' => 'Done',
                            default => 'Open task',
                        };
                        ?>
                        <tr>
                            <td><a href="<?= e(base_url('tasks/' . $task->id)) ?>"><strong><?= e($task->title) ?></strong></a></td>
                            <td><?= e($task->project_name ?: '') ?></td>
                            <td><?= e(ucfirst((string) $task->priority)) ?></td>
                            <td><?= e(format_date($task->due_date)) ?></td>
                            <td><?php \App\Core\View::component('status-badge', [
                                'status' => $task->status,
                                'label' => $task->status_name ?: null,
                                'color' => $task->status_color ?: null,
                            ]); ?></td>
                            <td><a class="btn btn-sm btn-outline" href="<?= e(base_url('tasks/' . $task->id)) ?>"><?= e($next) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
