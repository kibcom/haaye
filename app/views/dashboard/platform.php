<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2><?= e(app_name()) ?> console</h2>
        <p class="text-muted mb-0">Manage organizations and platform administrators.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('platform.organizations.create')): ?>
            <a class="btn btn-aqua" href="<?= e(base_url('platform/organizations/create')) ?>"><?= icon('plus-lg') ?> New organization</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-2 mb-4">
    <?php if (can('platform.organizations.view')): ?>
        <div class="col-6 col-lg-3">
            <a class="wf-stat" href="<?= e(base_url('platform/organizations')) ?>">
                <?= icon('buildings') ?>
                <span>Organizations</span>
                <strong><?= (int) $organizationCount ?></strong>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="wf-stat" href="<?= e(base_url('platform/organizations?status=active')) ?>">
                <?= icon('building-check') ?>
                <span>Active organizations</span>
                <strong><?= (int) $activeOrganizationCount ?></strong>
            </a>
        </div>
    <?php endif; ?>
    <?php if (can('platform.users.view')): ?>
        <div class="col-6 col-lg-3">
            <a class="wf-stat" href="<?= e(base_url('platform/organizations')) ?>">
                <?= icon('people') ?>
                <span>All users</span>
                <strong><?= (int) $userCount ?></strong>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="wf-stat" href="<?= e(base_url('platform/users')) ?>">
                <?= icon('person-gear') ?>
                <span>Platform users</span>
                <strong><?= (int) $platformUserCount ?></strong>
            </a>
        </div>
    <?php endif; ?>
</div>

<section class="wf-card">
    <div class="wf-card-head">
        <h3>Recent activity</h3>
    </div>
    <?php if (empty($activity)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No platform activity yet',
            'message' => 'Organization and authentication events will appear here.',
        ]); ?>
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
