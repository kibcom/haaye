<?php
$links = [
    'task' => 'tasks/' . $approval->entity_id,
    'project' => 'projects/' . $approval->project_id,
    'deliverable' => 'deliverables/' . $approval->entity_id,
    'milestone' => 'projects/' . $approval->project_id,
];
$target = $links[$approval->entity_type] ?? null;
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker"><?= e(ucfirst($approval->approval_kind)) ?> approval</p>
        <h2><?= e($approval->entity_label) ?></h2>
        <p class="text-muted mb-0"><?= e(ucfirst($approval->entity_type)) ?> · <?= e(ucwords(str_replace('_', ' ', $approval->from_status))) ?> → <?= e(ucwords(str_replace('_', ' ', $approval->to_status))) ?></p>
    </div>
    <?php \App\Core\View::component('status-badge', ['status' => $approval->status]); ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card">
            <dl class="wf-dl">
                <div><dt>Requested by</dt><dd><?= e($approval->requester_name ?: '') ?></dd></div>
                <div><dt>Requested</dt><dd><?= e(format_datetime($approval->created_at)) ?></dd></div>
                <div><dt>Decided by</dt><dd><?= e($approval->decider_name ?: '') ?></dd></div>
                <div><dt>Decided</dt><dd><?= e(format_datetime($approval->decided_at)) ?></dd></div>
                <?php if ($target && ($approval->entity_type !== 'task' || can('tasks.view')) && ($approval->entity_type !== 'project' && $approval->entity_type !== 'milestone' || can('projects.view') || can('projects.view_all')) && ($approval->entity_type !== 'deliverable' || can('deliverables.view'))): ?>
                    <div><dt>Record</dt><dd><a href="<?= e(base_url($target)) ?>">Open <?= e($approval->entity_type) ?></a></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($approval->decision_comment): ?>
                <p class="mt-3 mb-0"><?= nl2br(e($approval->decision_comment)) ?></p>
            <?php endif; ?>
        </section>
    </div>
    <?php if ($canDecide): ?>
        <div class="col-lg-5">
            <section class="wf-card">
                <div class="wf-card-head"><h3>Decision</h3></div>
                <form method="post" action="<?= e(base_url('approvals/' . $approval->id . '/decide')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="decision">Outcome</label>
                        <select class="form-select" id="decision" name="decision" required>
                            <option value="approved">Approved</option>
                            <option value="changes_requested">Changes requested</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="comment">Comment</label>
                        <textarea class="form-control" id="comment" name="comment" rows="3" maxlength="2000"></textarea>
                        <div class="form-text">Required unless you approve.</div>
                    </div>
                    <button class="btn btn-primary" type="submit" data-confirm="Are you sure you want to record this decision?"><?= icon('check2-square') ?> Record decision</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
