<?php \App\Core\View::component('settings-nav', []); ?>
<div class="wf-page-header wf-roles-head">
    <div>
        <p class="wf-kicker">Settings · <?= e($role->name) ?></p>
        <h2>Edit access</h2>
        <p class="text-muted mb-0">
            Tick boxes for this role. For Sales: <strong>View all leads/clients</strong> = whole company;
            leave them off = only accounts they own or are assigned to.
        </p>
    </div>
    <a class="btn btn-sm btn-outline" href="<?= e(base_url('roles/' . $role->id)) ?>"><?= icon('arrow-left') ?> Back</a>
</div>

<form method="post" action="<?= e(base_url('roles/' . $role->id)) ?>" class="wf-role-edit-form">
    <?= csrf_field() ?>
    <?php \App\Core\View::component('permission-checks', [
        'permissionGroups' => $permissionGroups,
        'selectedPermissionIds' => $selectedPermissionIds,
        'customEnabled' => true,
        'canCustomize' => false,
        'compact' => true,
        'headline' => 'Permissions',
        'subhead' => 'Search, then select or unselect.',
    ]); ?>
    <div class="wf-role-edit-actions">
        <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save access</button>
        <a class="btn btn-secondary" href="<?= e(base_url('roles/' . $role->id)) ?>">Cancel</a>
    </div>
</form>
