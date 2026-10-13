<?php
$nextPending = null;
foreach ($stages as $stage) {
    if ($stage->status === 'pending') {
        $nextPending = $stage;
        break;
    }
}
$activeStage = null;
foreach ($stages as $stage) {
    if (in_array($stage->status, ['ready', 'in_progress', 'submitted', 'changes_requested'], true)) {
        $activeStage = $stage;
        break;
    }
}
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">CEO pipeline</p>
        <h2><?= e($run->name) ?></h2>
        <p class="text-muted mb-0">
            <a href="<?= e(base_url('projects/' . $run->project_id)) ?>"><?= e($run->project_name ?: 'Project') ?></a>
            · <?= e($run->client_name ?: '') ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <?php \App\Core\View::component('status-badge', ['status' => $run->status]); ?>
        <?php if (!empty($canManage) && in_array($run->status, ['active', 'paused'], true)): ?>
            <form method="post" action="<?= e(base_url('pipeline/runs/' . $run->id . '/pause')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline" type="submit">Pause</button>
            </form>
            <form method="post" action="<?= e(base_url('pipeline/runs/' . $run->id . '/cancel')) ?>" data-confirm="Cancel this entire workflow?">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-soft-danger" type="submit">Cancel</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($pendingApprovals)): ?>
    <section class="wf-card mb-3 border-primary">
        <div class="wf-card-head"><h3>Waiting for your approval</h3></div>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead><tr><th>Task</th><th>Submitted by</th><th>When</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pendingApprovals as $approval): ?>
                    <tr>
                        <td><strong><?= e($approval->entity_label) ?></strong></td>
                        <td><?= e($approval->requester_name ?: '') ?></td>
                        <td><?= e(format_datetime($approval->created_at)) ?></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end flex-wrap">
                                <form method="post" action="<?= e(base_url('approvals/' . $approval->id . '/decide')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="approved">
                                    <input type="hidden" name="return_to" value="pipeline/runs/<?= (int) $run->id ?>">
                                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('check-lg') ?> Approve</button>
                                </form>
                                <a class="btn btn-sm btn-outline" href="<?= e(base_url('approvals/' . $approval->id)) ?>">Request changes</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($canManage) && $nextPending && !$activeStage && in_array($run->status, ['active', 'paused'], true)): ?>
    <section class="wf-card mb-3">
        <div class="wf-card-head"><h3>Create next task</h3></div>
        <p class="text-muted small">Next stage: <strong><?= e($nextPending->name) ?></strong>. Assign someone and click once.</p>
        <form method="post" action="<?= e(base_url('pipeline/runs/' . $run->id . '/confirm-next')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label" for="assignee_user_id">Assign to</label>
                <select class="form-select" id="assignee_user_id" name="assignee_user_id" required>
                    <option value="">Select person…</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= selected($nextPending->assignee_user_id, $person->id) ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary" type="submit"><?= icon('play-fill') ?> Create &amp; assign task</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="wf-card">
    <div class="wf-card-head"><h3>Stages</h3></div>
    <div class="table-responsive">
        <table class="table wf-table align-middle">
            <thead>
            <tr><th>#</th><th>Stage</th><th>Status</th><th>Assignee</th><th>Task</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($stages as $stage): ?>
                <tr>
                    <td><?= (int) $stage->sort_order ?></td>
                    <td>
                        <strong><?= e($stage->name) ?></strong>
                        <?php if ($stage->instructions): ?>
                            <div class="text-muted small"><?= e(mb_strimwidth((string) $stage->instructions, 0, 90, '…')) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php \App\Core\View::component('status-badge', ['status' => $stage->status]); ?></td>
                    <td>
                        <?= e($stage->assignee_name ?: 'Unassigned') ?>
                        <?php if (!empty($canReassign) && !in_array($stage->status, ['approved', 'cancelled', 'skipped'], true)): ?>
                            <form method="post" action="<?= e(base_url('pipeline/runs/' . $run->id . '/stages/' . $stage->id . '/reassign')) ?>" class="mt-1 d-flex gap-1">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm" name="assignee_user_id" style="max-width:160px" required>
                                    <option value="">Select…</option>
                                    <?php foreach ($people as $person): ?>
                                        <option value="<?= (int) $person->id ?>" <?= selected($stage->assignee_user_id, $person->id) ?>><?= e($person->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline" type="submit">Set</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($stage->task_id): ?>
                            <a href="<?= e(base_url('tasks/' . $stage->task_id)) ?>"><?= e($stage->task_title ?: ('#' . $stage->task_id)) ?></a>
                            <?php if ($stage->task_status): ?>
                                <div class="small text-muted"><?= e(ucwords(str_replace('_', ' ', $stage->task_status))) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small">
                        <?php if ($stage->activated_at): ?>Activated <?= e(format_datetime($stage->activated_at)) ?><?php endif; ?>
                        <?php if ($stage->approved_at): ?><br>Approved <?= e(format_datetime($stage->approved_at)) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
