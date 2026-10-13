<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Decisions</p>
        <h2>Approvals</h2>
        <p class="text-muted mb-0">Pending, approved, rejected, withdrawn, and changes requested.</p>
    </div>
</div>

<form class="wf-filters" method="get">
    <select class="form-select" name="status">
        <option value="">All decisions</option>
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'changes_requested' => 'Changes requested', 'cancelled' => 'Withdrawn'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= selected($status ?? 'pending', $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($approvals)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No approvals',
            'message' => 'Requests appear here when a workflow move needs a decision.',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Move</th>
                    <th>Kind</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($approvals as $approval): ?>
                    <tr>
                        <td><a href="<?= e(base_url('approvals/' . $approval->id)) ?>"><strong><?= e($approval->entity_label) ?></strong></a></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $approval->from_status))) ?> → <?= e(ucwords(str_replace('_', ' ', $approval->to_status))) ?></td>
                        <td><?= e(ucfirst($approval->approval_kind)) ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $approval->status]); ?></td>
                        <td><?= e($approval->requester_name ?: '') ?><div class="text-muted small"><?= e(format_datetime($approval->created_at)) ?></div></td>
                        <td class="text-end">
                            <?php if ($approval->status === 'pending' && !empty($canDecideMap[(int) $approval->id])): ?>
                                <form method="post" action="<?= e(base_url('approvals/' . $approval->id . '/decide')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="decision" value="approved">
                                    <input type="hidden" name="return_to" value="approvals?status=pending">
                                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('check-lg') ?> Approve</button>
                                </form>
                                <a class="btn btn-sm btn-outline" href="<?= e(base_url('approvals/' . $approval->id)) ?>">Changes</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</section>
