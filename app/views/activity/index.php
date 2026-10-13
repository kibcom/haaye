<?php
$pages = (int) ceil(($total ?? 0) / max(1, (int) ($perPage ?? 40)));
$queryUser = (int) ($userId ?? 0);
$canSeeAll = !empty($canSeeAll);
$scopedToSelf = !empty($scopedToSelf);
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Workspace</p>
        <h2>Activity</h2>
        <p class="text-muted mb-0">
            <?php if ($scopedToSelf): ?>
                Your updates, plus approvals and changes on tasks assigned to you.
            <?php else: ?>
                Who changed what across the company. Filter by employee when needed.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($canSeeAll): ?>
<form class="wf-filters" method="get">
    <select class="form-select" name="user">
        <option value="">All employees</option>
        <?php foreach ($employees as $person): ?>
            <option value="<?= (int) $person['id'] ?>" <?= selected($queryUser, $person['id']) ?>><?= e($person['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<section class="wf-card">
    <?php if (empty($logs)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No activity yet',
            'message' => $scopedToSelf
                ? 'Your task updates, submissions, and approval decisions will show here.'
                : 'Updates by employees will appear here and stay on record.',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table wf-keep align-middle mb-0">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>What changed</th>
                    <th>When</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <strong><?= e($log->actor_name ?: 'System') ?></strong>
                            <?php if ($log->actor_role): ?><div class="text-muted small"><?= e($log->actor_role) ?></div><?php endif; ?>
                        </td>
                        <td><?= e($log->description) ?></td>
                        <td><?= e(format_datetime($log->created_at)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="d-flex gap-2 mt-3">
                <?php if ($page > 1): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(base_url('activity?user=' . $queryUser . '&page=' . ($page - 1))) ?>"><?= icon('chevron-left') ?> Newer</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-secondary btn-sm" href="<?= e(base_url('activity?user=' . $queryUser . '&page=' . ($page + 1))) ?>">Older <?= icon('chevron-right') ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
