<?php
$day = new DateTimeImmutable($weekStart);
$queryUser = (int) $subject->id;
$mine = (int) $subject->id === (int) \App\Core\Auth::id();
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Time</p>
        <h2><?= $mine ? 'My timesheet' : e($subject->name) ?></h2>
        <p class="text-muted mb-0"><?= e($weekLabel) ?> · <?= e(format_minutes((int) $minutes)) ?> logged</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('time.view_all')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('time/workload?week=' . $weekStart)) ?>"><?= icon('people') ?> Workload</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(base_url('time/report')) ?>"><?= icon('graph-up-arrow') ?> Productivity</a>
        <?php if (can('time.create')): ?>
            <a class="btn btn-primary" href="<?= e(base_url('time/log')) ?>"><?= icon('clock') ?> Log time</a>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('time?week=' . $previousWeek . '&user=' . $queryUser)) ?>"><?= icon('chevron-left') ?> Previous week</a>
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('time?user=' . $queryUser)) ?>">This week</a>
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('time?week=' . $nextWeek . '&user=' . $queryUser)) ?>"><?= icon('chevron-right') ?> Next week</a>
    </div>
    <?php if ($people): ?>
        <form method="get" action="<?= e(base_url('time')) ?>" class="d-flex gap-2">
            <input type="hidden" name="week" value="<?= e($weekStart) ?>">
            <label class="visually-hidden" for="timesheet-user">Teammate</label>
            <select class="form-select form-select-sm" id="timesheet-user" name="user" onchange="this.form.submit()">
                <?php foreach ($people as $person): ?>
                    <option value="<?= (int) $person->id ?>" <?= (int) $person->id === $queryUser ? 'selected' : '' ?>><?= e($person->name) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<?php if ($running && $mine): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span>Timer running on <?= e($running->task_title ?: $running->project_name) ?> since <?= e(date('g:i A', strtotime($running->started_at))) ?>.</span>
        <form method="post" action="<?= e(base_url('time/stop')) ?>" class="mb-0">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="time">
            <button class="btn btn-sm btn-primary" type="submit"><?= icon('stop-circle') ?> Stop</button>
        </form>
    </div>
<?php endif; ?>

<?php for ($i = 0; $i < 7; $i++): ?>
    <?php
    $current = $day->modify('+' . $i . ' days');
    $key = $current->format('Y-m-d');
    $items = $byDay[$key] ?? [];
    $dayMinutes = 0;
    foreach ($items as $item) {
        $dayMinutes += (int) $item->duration_minutes;
    }
    ?>
    <section class="wf-card mb-3">
        <div class="wf-card-head">
            <h3><?= e($current->format('l, M j')) ?></h3>
            <span class="text-muted"><?= e(format_minutes($dayMinutes)) ?></span>
        </div>
        <?php if ($items === []): ?>
            <p class="text-muted mb-0">No time logged.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Work</th>
                            <th>Duration</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $entry): ?>
                            <tr>
                                <td>
                                    <?php if ($entry->isRunning()): ?>
                                        Running
                                    <?php else: ?>
                                        <?= e(date('g:i A', strtotime($entry->started_at))) ?> – <?= e(date('g:i A', strtotime((string) $entry->ended_at))) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= e($entry->task_title ?: $entry->project_name) ?></div>
                                    <div class="text-muted small">
                                        <?= e($entry->project_code) ?> · <?= e($entry->project_name) ?>
                                        <?= (int) $entry->billable === 1 ? ' · Billable' : ' · Non-billable' ?>
                                    </div>
                                    <?php if ($entry->note): ?>
                                        <div class="small"><?= e($entry->note) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $entry->isRunning() ? '' : e(format_minutes((int) $entry->duration_minutes)) ?></td>
                                <td class="text-end">
                                    <?php if (\App\Services\TimeAccess::canEdit($entry)): ?>
                                        <a href="<?= e(base_url('time/' . $entry->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
                                    <?php endif; ?>
                                    <?php if (\App\Services\TimeAccess::canDelete($entry)): ?>
                                        <form method="post" action="<?= e(base_url('time/' . $entry->id . '/delete')) ?>" class="d-inline" data-confirm="Are you sure you want to delete this time entry?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endfor; ?>
