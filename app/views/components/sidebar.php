<?php
$current = \App\Core\Request::path();
$isPlatform = is_super_admin();
$user = auth_user();
$organization = $isPlatform ? null : $user?->organization();

$active = static function (string $prefix) use ($current): string {
    if ($prefix === '/dashboard' && ($current === '/' || $current === '/dashboard')) {
        return 'is-active';
    }
    if ($prefix === '/platform') {
        return $current === '/platform' ? 'is-active' : '';
    }
    if ($current === $prefix || str_starts_with($current, $prefix . '/')) {
        return 'is-active';
    }
    return '';
};

$item = static function (string $path, string $label, string $iconName) use ($active): void {
    echo '<a class="wf-nav-link ' . $active('/' . $path) . '" href="' . e(base_url($path)) . '" data-label="' . e($label) . '">';
    echo icon($iconName);
    echo '<span>' . e($label) . '</span></a>';
};

$settingsOpen = $current === '/settings'
    || str_starts_with($current, '/settings/')
    || $current === '/roles'
    || str_starts_with($current, '/roles/')
    || $current === '/guide'
    || str_starts_with($current, '/guide/')
    || $current === '/platform/guide'
    || str_starts_with($current, '/platform/guide/');
$companyLogo = (!$isPlatform && !empty($organization?->logo)) ? (string) $organization->logo : '';
$workspaces = (!$isPlatform && $user) ? \App\Models\User::workspacesForEmail($user->email) : [];
$workspaceAllowance = (!$isPlatform && $user) ? \App\Services\FeatureService::workspaceAllowance($user->email) : null;
$workspaceMark = static function (string $name, string $logo): void {
    echo '<span class="wf-workspace-mark">';
    if ($logo !== '') {
        echo '<img src="' . e(asset($logo)) . '" alt="">';
    } else {
        echo '<span>' . e(user_initials($name)) . '</span>';
    }
    echo '</span>';
};
$productLogo = static function (string $extraClass = ''): void {
    echo '<div class="wf-sidebar-logo' . ($extraClass !== '' ? ' ' . e($extraClass) : '') . '">';
    echo '<img class="wf-sidebar-mark" src="' . e(asset('images/haaye-hero-mark.png')) . '" alt="">';
    echo '<div class="wf-sidebar-logo-text"><strong>Haaye <span>Manager</span></strong><small>Plan · Track · Achieve</small></div>';
    echo '</div>';
};
?>
<aside class="wf-sidebar" id="wf-sidebar">
    <div class="wf-sidebar-brand">
        <?php if (!$isPlatform && $organization): ?>
            <div class="wf-workspace">
                <button class="wf-workspace-btn" type="button" aria-expanded="false" aria-haspopup="listbox">
                    <?php $workspaceMark((string) $organization->name, $companyLogo); ?>
                    <span class="wf-workspace-copy">
                        <strong><?= e($organization->name) ?></strong>
                        <?php if ($organization->company_code): ?><small><?= e($organization->company_code) ?></small><?php endif; ?>
                    </span>
                    <?= icon('chevron-down') ?>
                </button>
                <div class="wf-workspace-menu" hidden>
                    <p class="wf-workspace-label">Select Company / Workspace</p>
                    <?php foreach ($workspaces as $workspace): ?>
                        <?php $isCurrent = (int) $workspace['user_id'] === (int) $user->id; ?>
                        <?php if ($isCurrent): ?>
                            <div class="wf-workspace-item is-current">
                                <?php $workspaceMark($workspace['name'], $workspace['logo']); ?>
                                <span class="wf-workspace-copy">
                                    <strong><?= e($workspace['name']) ?></strong>
                                    <?php if ($workspace['company_code'] !== ''): ?><small><?= e($workspace['company_code']) ?></small><?php endif; ?>
                                </span>
                                <?= icon('check-lg') ?>
                            </div>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('workspace/switch')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="account" value="<?= (int) $workspace['user_id'] ?>">
                                <button class="wf-workspace-item" type="submit">
                                    <?php $workspaceMark($workspace['name'], $workspace['logo']); ?>
                                    <span class="wf-workspace-copy">
                                        <strong><?= e($workspace['name']) ?></strong>
                                        <?php if ($workspace['company_code'] !== ''): ?><small><?= e($workspace['company_code']) ?></small><?php endif; ?>
                                    </span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (is_array($workspaceAllowance)): ?>
                        <p class="wf-workspace-note">
                            <?php if ($workspaceAllowance['limit'] === null): ?>
                                <?= (int) $workspaceAllowance['used'] ?> workspaces · no plan limit
                            <?php else: ?>
                                <?= (int) $workspaceAllowance['used'] ?> of <?= (int) $workspaceAllowance['limit'] ?> workspaces
                            <?php endif; ?>
                        </p>
                        <a class="btn btn-primary wf-workspace-add" href="<?= e(base_url('workspaces/create')) ?>">
                            <?= icon('plus-lg') ?> Add new workspace
                        </a>
                    <?php endif; ?>
                    <?php if (can('organization.update')): ?>
                        <a class="wf-workspace-manage" href="<?= e(base_url('settings/organization')) ?>">
                            <?= icon('gear') ?> Manage companies
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php $productLogo(); ?>
            <div class="wf-brand-copy">
                <span class="wf-company-name"><?= $isPlatform ? 'Platform' : e($organization?->name ?? 'Workspace') ?></span>
            </div>
        <?php endif; ?>
    </div>

    <nav class="wf-nav">
        <?php if ($isPlatform): ?>
            <p class="wf-nav-label">Platform</p>
            <?php $item('platform', 'Dashboard', 'house'); ?>
            <?php if (can('platform.organizations.view')): ?>
                <?php $item('platform/organizations', 'Organizations', 'buildings'); ?>
            <?php endif; ?>
            <?php if (can('platform.plans.view')): ?>
                <?php $item('platform/plans', 'Plans', 'sliders'); ?>
                <?php $pendingPlans = \App\Services\PlanUpgradeService::pendingCount(); ?>
                <a class="wf-nav-link <?= $active('/platform/plan-requests') ?>" href="<?= e(base_url('platform/plan-requests')) ?>" data-label="Plan requests"><?= icon('inbox') ?><span>Plan requests<?= $pendingPlans > 0 ? ' (' . (int) $pendingPlans . ')' : '' ?></span></a>
            <?php endif; ?>
            <?php if (can('platform.users.view')): ?>
                <?php $item('platform/users', 'Platform users', 'people'); ?>
            <?php endif; ?>
            <a class="wf-nav-link <?= $settingsOpen ? 'is-active' : '' ?>" href="<?= e(base_url('settings')) ?>" data-label="Settings"><?= icon('gear') ?><span>Settings</span></a>
        <?php else: ?>
            <?php if (can('portal.view')): ?>
                <p class="wf-nav-label">Portal</p>
                <?php if (can('dashboard.view')): ?>
                    <?php $item('dashboard', 'Dashboard', 'house'); ?>
                <?php endif; ?>
                <?php if (feature_enabled('portal')): ?>
                <?php $item('portal/company', 'Company', 'building'); ?>
                <?php if (feature_enabled('projects')): ?>
                    <?php $item('portal/projects', 'Projects', 'folder'); ?>
                <?php endif; ?>
                <?php if (can('deliverables.view') && feature_enabled('deliverables')): ?>
                    <?php $item('deliverables', 'Deliverables', 'box-seam'); ?>
                <?php endif; ?>
                <?php if (can('approvals.view') && feature_enabled('approvals')): ?>
                    <?php $item('approvals', 'Approvals', 'check2-circle'); ?>
                <?php endif; ?>
                <?php if (feature_enabled('invoices')): ?>
                    <?php $item('portal/invoices', 'Invoices', 'receipt'); ?>
                    <?php $item('portal/payments', 'Payments', 'credit-card'); ?>
                <?php endif; ?>
                <?php $item('portal/files', 'Files', 'paperclip'); ?>
                <?php $item('portal/notifications', 'Notifications', 'bell'); ?>
                <?php endif; ?>
            <?php else: ?>
            <?php
            $showOverview = can('dashboard.view') || can('activity.view');
            $showCrm = (can('leads.view') && feature_enabled('leads'))
                || (can('work_flow.view') && feature_enabled('work_flow'))
                || ((can('work_flow.assign') || can('work_flow.manage')) && feature_enabled('work_flow'))
                || (can('clients.view') && feature_enabled('clients'));
            $showOperations = ((can('projects.view') || can('projects.view_all')) && feature_enabled('projects'))
                || (can('bookings.view') && feature_enabled('bookings'))
                || (can('tasks.view') && feature_enabled('tasks'))
                || ((can('workspace.view') || can('tasks.view')) && feature_enabled('tasks'))
                || (can('pipeline.view') && feature_enabled('pipeline'))
                || (can('calendar.view') && feature_enabled('tasks'))
                || (can('time.view') && feature_enabled('time'));
            $showFinance = (can('finance.view') && feature_enabled('finance'))
                || (can('invoices.view') && feature_enabled('invoices'))
                || (can('expenses.view') && feature_enabled('expenses'));
            $showDelivery = (can('deliverables.view') && feature_enabled('deliverables'))
                || (can('approvals.view') && feature_enabled('approvals'))
                || (can('workflows.view') && feature_enabled('workflows'));
            $showPeople = can('users.view');
            ?>
            <?php if ($showOverview): ?>
                <p class="wf-nav-label">Overview</p>
                <?php if (can('dashboard.view')): ?>
                    <?php $item('dashboard', 'Dashboard', 'house'); ?>
                <?php endif; ?>
                <?php if (can('workspace.view') || can('tasks.view')): ?>
                    <?php if (feature_enabled('tasks')): ?>
                        <?php $item('my-work', 'My Work', 'briefcase'); ?>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (can('activity.view')): ?>
                    <?php $item('activity', 'Activity', 'clock-history'); ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showCrm): ?>
                <p class="wf-nav-label">CRM</p>
                <?php if (can('leads.view') && feature_enabled('leads')): ?>
                    <?php $item('leads', 'Leads', 'funnel'); ?>
                <?php endif; ?>
                <?php if (can('work_flow.view') && feature_enabled('work_flow')): ?>
                    <?php $item('work-flow', 'Workflow board', 'diagram-3'); ?>
                <?php endif; ?>
                <?php if ((can('work_flow.assign') || can('work_flow.manage')) && feature_enabled('work_flow')): ?>
                    <?php $item('work-flow/content-grids', 'Content grids', 'calendar3'); ?>
                <?php endif; ?>
                <?php if (can('clients.view') && feature_enabled('clients')): ?>
                    <?php $item('clients', 'Clients', 'person-vcard'); ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showOperations): ?>
                <p class="wf-nav-label">Operations</p>
                <?php if ((can('projects.view') || can('projects.view_all')) && feature_enabled('projects')): ?>
                    <?php $item('projects', 'Projects', 'folder'); ?>
                <?php endif; ?>
                <?php if (can('bookings.view') && feature_enabled('bookings')): ?>
                    <?php $item('bookings', 'Bookings', 'calendar2-event'); ?>
                <?php endif; ?>
                <?php if (can('tasks.view') && feature_enabled('tasks')): ?>
                    <?php $item('tasks', 'Tasks', 'check2-square'); ?>
                <?php endif; ?>
                <?php if (can('pipeline.view') && feature_enabled('pipeline')): ?>
                    <?php $item('pipeline', 'Pipeline', 'layers'); ?>
                <?php endif; ?>
                <?php if (can('calendar.view') && feature_enabled('tasks')): ?>
                    <?php $item('calendar', 'Calendar', 'calendar3'); ?>
                <?php endif; ?>
                <?php if (can('time.view') && feature_enabled('time')): ?>
                    <?php $item('time', 'Timesheet', 'clock'); ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showFinance): ?>
                <p class="wf-nav-label">Finance</p>
                <?php if (can('finance.view') && feature_enabled('finance')): ?>
                    <?php $item('finance', 'Finance', 'wallet2'); ?>
                <?php endif; ?>
                <?php if (can('invoices.view') && feature_enabled('invoices')): ?>
                    <?php $item('invoices', 'Invoices', 'receipt'); ?>
                <?php endif; ?>
                <?php if (can('expenses.view') && feature_enabled('expenses')): ?>
                    <?php $item('expenses', 'Expenses', 'cash-stack'); ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showDelivery): ?>
                <p class="wf-nav-label">Delivery</p>
                <?php if (can('deliverables.view') && feature_enabled('deliverables')): ?>
                    <?php $item('deliverables', 'Deliverables', 'box-seam'); ?>
                <?php endif; ?>
                <?php if (can('approvals.view') && feature_enabled('approvals')): ?>
                    <?php $item('approvals', 'Approvals', 'check2-circle'); ?>
                <?php endif; ?>
                <?php if (can('workflows.view') && feature_enabled('workflows')): ?>
                    <?php $item('workflows', 'Status rules', 'diagram-3'); ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($showPeople): ?>
                <p class="wf-nav-label">People</p>
                <?php $item('users', 'People', 'people'); ?>
            <?php endif; ?>
            <p class="wf-nav-label">System</p>
            <a class="wf-nav-link <?= $settingsOpen ? 'is-active' : '' ?>" href="<?= e(base_url('settings')) ?>" data-label="Settings"><?= icon('gear') ?><span>Settings</span></a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
    <div class="wf-sidebar-foot<?= $companyLogo !== '' ? ' has-product' : '' ?>" aria-label="Product version">
        <?php if ($companyLogo !== ''): ?>
            <?php $productLogo('is-foot'); ?>
        <?php endif; ?>
        <div class="wf-sidebar-meta">
            <span>KTLogy</span>
            <span class="wf-sidebar-ver">v 1.0</span>
        </div>
    </div>
</aside>
<div class="wf-sidebar-backdrop" id="wf-sidebar-backdrop"></div>
