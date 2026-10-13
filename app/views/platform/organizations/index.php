<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2>Organizations</h2>
        <p class="text-muted mb-0">Tenant companies using <?= e(app_name()) ?>.</p>
    </div>
    <?php if (can('platform.organizations.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('platform/organizations/create')) ?>"><?= icon('plus-lg') ?> New organization</a>
    <?php endif; ?>
</div>

<form class="wf-filters" method="get">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search organizations">
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <option value="active" <?= selected($status ?? '', 'active') ?>>Active</option>
        <option value="inactive" <?= selected($status ?? '', 'inactive') ?>>Inactive</option>
        <option value="suspended" <?= selected($status ?? '', 'suspended') ?>>Suspended</option>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($organizations)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No organizations yet',
            'message' => 'Create the first tenant organization to begin.',
            'actionUrl' => 'platform/organizations/create',
            'actionLabel' => 'New organization',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Organization</th>
                    <th>Industry</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($organizations as $organization): ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url('platform/organizations/' . $organization->id)) ?>">
                                <strong><?= e($organization->name) ?></strong>
                            </a>
                            <div class="text-muted small"><?= e($organization->company_code ?: '') ?><?= $organization->email ? ' · ' . e($organization->email) : '' ?></div>
                        </td>
                        <td><?= e($organization->industry ?: '') ?></td>
                        <td><?= e($organization->subscription_plan) ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $organization->status]); ?></td>
                        <td><?= e(format_date($organization->created_at)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</section>
