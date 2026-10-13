<?php
/** @var list<\App\Models\Role> $roles */
/** @var array<int, array{clients:bool,clientsAll:bool,workflow:bool,grids:bool,manage:bool,customized:bool}> $roleFeatureAccess */
$roleFeatureAccess = $roleFeatureAccess ?? [];
$canEdit = !empty($canEdit);
?>
<?php \App\Core\View::component('settings-nav', []); ?>
<div class="wf-page-header wf-roles-head">
    <div>
        <p class="wf-kicker">Settings</p>
        <h2>Roles</h2>
        <p class="text-muted mb-0">Toggle what each role can see. Sales: leave “view all” off to show only their own leads/clients.</p>
    </div>
</div>

<p class="wf-roles-legend">
    <span><i></i><b>Clients</b></span>
    <span><i class="is-all"></i><b>All CRM</b> company-wide</span>
    <span><i class="is-wf"></i><b>Workflow</b></span>
    <span><i class="is-grid"></i><b>Grids</b></span>
    <?php if ($canEdit): ?><span class="text-muted">Click a role to edit access</span><?php endif; ?>
</p>

<div class="wf-roles-grid">
    <?php foreach ($roles as $role): ?>
        <?php
        $access = $roleFeatureAccess[(int) $role->id] ?? [
            'clients' => false, 'clientsAll' => false, 'workflow' => false,
            'grids' => false, 'manage' => false, 'customized' => false,
        ];
        ?>
        <a class="wf-role-tile" href="<?= e(base_url('roles/' . $role->id . ($canEdit ? '/edit' : ''))) ?>">
            <div class="wf-role-tile-top">
                <strong><?= e($role->name) ?></strong>
                <?php if (!empty($access['customized'])): ?><em>Custom</em><?php endif; ?>
            </div>
            <div class="wf-role-tile-chips">
                <?php if ($access['clients']): ?><span class="is-on">Clients<?= !empty($access['clientsAll']) ? ' · all' : ' · own' ?></span><?php endif; ?>
                <?php if ($access['workflow']): ?><span class="is-wf">Workflow</span><?php endif; ?>
                <?php if ($access['grids']): ?><span class="is-grid">Grids</span><?php endif; ?>
                <?php if ($access['manage']): ?><span class="is-on">Manage</span><?php endif; ?>
                <?php if (!$access['clients'] && !$access['workflow'] && !$access['grids']): ?>
                    <span>Basic</span>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
