<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Time</p>
        <h2>Employee workload</h2>
        <p class="text-muted mb-0"><?= e($weekLabel) ?>. Open tasks are unfinished work. Hours are time logged this week.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(base_url('time?week=' . $weekStart)) ?>"><?= icon('calendar-week') ?> Timesheet</a>
</div>

<div class="btn-group mb-3">
    <a class="btn btn-outline btn-sm" href="<?= e(base_url('time/workload?week=' . $previousWeek)) ?>"><?= icon('chevron-left') ?> Previous week</a>
    <a class="btn btn-primary btn-sm" href="<?= e(base_url('time/workload')) ?>">This week</a>
    <a class="btn btn-outline btn-sm" href="<?= e(base_url('time/workload?week=' . $nextWeek)) ?>"><?= icon('chevron-right') ?> Next week</a>
</div>

<section class="wf-card">
    <?php if ($rows === []): ?>
        <?php $title = 'No teammates yet'; $message = 'Active employees will appear here.'; include BASE_PATH . '/app/views/components/empty-state.php'; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Open tasks</th>
                        <th>Hours this week</th>
                        <th>Billable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= e(base_url('time?week=' . $weekStart . '&user=' . $row['user']->id)) ?>"><?= e($row['user']->name) ?></a>
                                <div class="text-muted small"><?= e($row['user']->job_title ?: $row['user']->role_name) ?></div>
                            </td>
                            <td><?= (int) $row['open_tasks'] ?></td>
                            <td><?= e(format_minutes((int) $row['minutes'])) ?></td>
                            <td><?= e(format_minutes((int) $row['billable_minutes'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
