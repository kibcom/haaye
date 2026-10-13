<div class="wf-page-header">
    <div>
        <p class="wf-kicker">People</p>
        <h2><?= e($user->name) ?></h2>
        <p class="text-muted mb-0"><?= e($user->email) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('users.update')): ?>
            <a class="btn btn-soft-primary" href="<?= e(base_url('users/' . $user->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
        <?php endif; ?>
        <?php if (can('users.activate') && (int) $user->id !== (int) auth_user()?->id): ?>
            <form method="post" action="<?= e(base_url('users/' . $user->id . '/' . ($user->status === 'active' ? 'deactivate' : 'activate'))) ?>" data-confirm="<?= $user->status === 'active' ? 'Are you sure you want to deactivate this person?' : 'Are you sure you want to activate this person?' ?>">
                <?= csrf_field() ?>
                <button class="btn <?= $user->status === 'active' ? 'btn-warning' : 'btn-success' ?>" type="submit">
                    <?= $user->status === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="wf-card wf-person">
    <div class="wf-person-layout">
        <div>
            <?php if ($user->profile_photo): ?>
                <img class="wf-avatar-lg" src="<?= e(asset($user->profile_photo)) ?>" alt="">
            <?php else: ?>
                <div class="wf-avatar-lg wf-avatar-fallback"><?= e(user_initials($user->name)) ?></div>
            <?php endif; ?>
        </div>
        <div>
            <dl class="wf-dl">
                <div><dt>Role</dt><dd><?= e($user->role_name ?? '') ?></dd></div>
                <div><dt>Job title</dt><dd><?= e($user->job_title ?: '') ?></dd></div>
                <div><dt>Employee ID</dt><dd><?= e($user->employee_code ?: '') ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($user->phone ?: '') ?></dd></div>
                <div><dt>Status</dt><dd><?php \App\Core\View::component('status-badge', ['status' => $user->status]); ?></dd></div>
                <div><dt>Joined</dt><dd><?= e(format_date($user->joining_date)) ?></dd></div>
                <div><dt>Last login</dt><dd><?= e(format_datetime($user->last_login_at)) ?></dd></div>
                <div><dt>Access</dt><dd><?= !empty($customPermissions) ? 'Custom for this person' : 'Role defaults' ?></dd></div>
            </dl>
        </div>
    </div>
</section>

<?php if (!empty($isDemoUser) && !empty($demoPassword)): ?>
<section class="wf-card mt-3">
    <div class="wf-card-head"><h3>Demo login</h3></div>
    <p class="text-muted small mb-3">
        This person belongs to
        <strong><?= e($organization->name ?? 'this company') ?></strong>
        (company id <?= (int) ($user->organization_id ?? 0) ?>).
        Login uses email + password — data stays inside that company.
    </p>
    <dl class="wf-dl">
        <div><dt>Company</dt><dd><?= e($organization->name ?? '') ?><?php if (!empty($organization->slug)): ?> · <code><?= e((string) $organization->slug) ?></code><?php endif; ?></dd></div>
        <div><dt>Email</dt><dd><code><?= e($user->email) ?></code></dd></div>
        <div><dt>Password</dt><dd><code><?= e($demoPassword) ?></code></dd></div>
    </dl>
</section>
<?php endif; ?>

<?php if (!empty($effectivePermissions)): ?>
    <section class="wf-card mt-3">
        <div class="wf-card-head">
            <h3>What they can access</h3>
            <?php if (can('users.update') && (can('roles.assign') || can('roles.update'))): ?>
                <a href="<?= e(base_url('users/' . $user->id . '/edit')) ?>"><?= icon('pencil') ?> Edit access</a>
            <?php endif; ?>
        </div>
        <?php foreach ($effectivePermissions as $group => $items): ?>
            <h4 class="wf-group-title"><?= e($group) ?></h4>
            <ul class="wf-perm-list">
                <?php foreach ($items as $item): ?>
                    <li><?= e($item['name']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
