<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Deliverable</p>
        <h2><?= e($deliverable->name) ?></h2>
        <p class="text-muted mb-0">
            <?php if (can('portal.view')): ?>
                <a href="<?= e(base_url('portal/projects/' . $deliverable->project_id)) ?>"><?= e($deliverable->project_name ?: 'Project') ?></a>
            <?php elseif (can('projects.view') || can('projects.view_all')): ?>
                <a href="<?= e(base_url('projects/' . $deliverable->project_id)) ?>"><?= e($deliverable->project_name ?: 'Project') ?></a>
            <?php else: ?>
                <?= e($deliverable->project_name ?: 'Project') ?>
            <?php endif; ?>
            · <?= e($deliverable->client_name ?: 'Client') ?> · Version <?= (int) $deliverable->version ?>
        </p>
    </div>
    <?php if ($canUpdate): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-soft-primary" href="<?= e(base_url('deliverables/' . $deliverable->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
            <form method="post" action="<?= e(base_url('deliverables/' . $deliverable->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this deliverable?">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit"><?= icon('trash3') ?> Delete</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="wf-stat">
            <span>Status</span>
            <strong><?= e($deliverable->status_name ?: ucwords(str_replace('_', ' ', $deliverable->status))) ?></strong>
        </div>
    </div>
    <?php if (\App\Services\ApprovalService::viewerClientId() === null): ?>
        <div class="col-md-4">
            <div class="wf-stat">
                <span>Internal approval</span>
                <strong><?= e($deliverable->internal_approval_status === 'none' ? 'Not requested' : ucwords(str_replace('_', ' ', $deliverable->internal_approval_status))) ?></strong>
            </div>
        </div>
    <?php endif; ?>
    <div class="col-md-3">
        <div class="wf-stat">
            <span>Client approval</span>
            <strong><?= e($deliverable->client_approval_status === 'none' ? 'Not requested' : ucwords(str_replace('_', ' ', $deliverable->client_approval_status))) ?></strong>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat">
            <span>Due</span>
            <strong><?= e(format_date($deliverable->due_date)) ?></strong>
            <small><?= e($deliverable->assignee_name ?: 'Unassigned') ?></small>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card mb-3">
            <?php if ($deliverable->description): ?>
                <p><?= nl2br(e($deliverable->description)) ?></p>
            <?php else: ?>
                <p class="text-muted">No description yet.</p>
            <?php endif; ?>
            <?php if ($deliverable->task_title && \App\Services\ApprovalService::viewerClientId() === null && can('tasks.view')): ?>
                <p class="mb-0">Task: <a href="<?= e(base_url('tasks/' . $deliverable->task_id)) ?>"><?= e($deliverable->task_title) ?></a></p>
            <?php elseif ($deliverable->task_title && \App\Services\ApprovalService::viewerClientId() === null): ?>
                <p class="mb-0">Task: <?= e($deliverable->task_title) ?></p>
            <?php endif; ?>
        </section>
        <?php if ($canUpdate): ?>
            <section class="wf-card mb-3">
                <div class="wf-card-head"><h3>Move status</h3></div>
                <form method="post" action="<?= e(base_url('deliverables/' . $deliverable->id . '/status')) ?>" class="row g-3" data-confirm="Are you sure you want to change this status?">
                    <?= csrf_field() ?>
                    <div class="col-md-8">
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $item): ?>
                                <option value="<?= e($item->slug) ?>" <?= selected($deliverable->status, $item->slug) ?>><?= e($item->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit"><?= icon('check-lg') ?> Update</button>
                    </div>
                </form>
                <p class="text-muted small mt-2 mb-0">If this move requires approval, the status stays here until that decision is recorded.</p>
            </section>
        <?php endif; ?>
        <section class="wf-card">
            <div class="wf-card-head"><h3>Approval history</h3></div>
                    <?php
                    $visibleApprovals = $approvals;
                    if (\App\Services\ApprovalService::viewerClientId() !== null) {
                        $visibleApprovals = array_values(array_filter($approvals, static function ($approval): bool {
                            return $approval->approval_kind === 'client';
                        }));
                    }
                    ?>
                    <?php if ($visibleApprovals === []): ?>
                <p class="text-muted mb-0">No approvals yet.</p>
            <?php else: ?>
                <ul class="wf-timeline">
                    <?php foreach ($visibleApprovals as $approval): ?>
                        <li>
                            <strong><?= e(ucfirst($approval->approval_kind)) ?> · <?php \App\Core\View::component('status-badge', ['status' => $approval->status]); ?></strong>
                            <span><?= e(ucwords(str_replace('_', ' ', $approval->from_status))) ?> → <?= e(ucwords(str_replace('_', ' ', $approval->to_status))) ?></span>
                            <?php if ($approval->decision_comment): ?><span><?= e($approval->decision_comment) ?></span><?php endif; ?>
                            <time><?= e($approval->decider_name ?: $approval->requester_name ?: '') ?> · <?= e(format_datetime($approval->decided_at ?: $approval->created_at)) ?></time>
                            <?php if (can('approvals.view')): ?>
                                <div><a href="<?= e(base_url('approvals/' . $approval->id)) ?>">Open decision</a></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="wf-card mb-3">
            <div class="wf-card-head"><h3>File</h3></div>
            <?php if ($deliverable->stored_path): ?>
                <p><a href="<?= e(base_url('deliverables/' . $deliverable->id . '/file')) ?>"><?= e($deliverable->original_name ?: 'Download') ?></a></p>
                <p class="text-muted small">Version <?= (int) $deliverable->version ?></p>
            <?php else: ?>
                <p class="text-muted">No file uploaded yet.</p>
            <?php endif; ?>
            <?php if ($canUpdate): ?>
                <form method="post" action="<?= e(base_url('deliverables/' . $deliverable->id . '/file')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input class="form-control mb-2" type="file" name="file" required aria-label="Upload a new version">
                    <button class="btn btn-outline" type="submit"><?= icon('upload') ?> Upload version</button>
                    <p class="text-muted small mt-2 mb-0">Images, PDF, text, CSV, Office, or ZIP. Up to 8 MB. A new upload increases the version.</p>
                </form>
            <?php endif; ?>
        </section>
        <?php if (\App\Services\ApprovalService::viewerClientId() === null): ?>
            <section class="wf-card">
                <div class="wf-card-head"><h3>Recent activity</h3></div>
                <?php if (empty($activity)): ?>
                    <p class="text-muted mb-0">Updates will appear here.</p>
                <?php else: ?>
                    <ul class="wf-timeline">
                        <?php foreach ($activity as $item): ?>
                            <li>
                                <strong><?= e($item->actor_name ?: 'System') ?></strong>
                                <span><?= e($item->description) ?></span>
                                <time><?= e(format_datetime($item->created_at)) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
