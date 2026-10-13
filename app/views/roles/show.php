<?php \App\Core\View::component('settings-nav', []); ?>
<?php
$total = 0;
foreach ($permissions as $items) {
    $total += count($items);
}
?>
<div class="wf-page-header wf-roles-head">
    <div>
        <p class="wf-kicker">Settings</p>
        <h2><?= e($role->name) ?></h2>
        <p class="text-muted mb-0"><?= e($role->description ?: 'Organization role') ?><?php if ($total > 0): ?> · <?= (int) $total ?> enabled<?php endif; ?></p>
    </div>
    <?php if (!empty($canEdit)): ?>
        <a class="btn btn-sm btn-soft-primary" href="<?= e(base_url('roles/' . $role->id . '/edit')) ?>"><?= icon('pencil') ?> Edit access</a>
    <?php endif; ?>
</div>

<?php if (empty($permissions)): ?>
    <section class="wf-card"><p class="text-muted mb-0">No permissions assigned.</p></section>
<?php else: ?>
    <div class="wf-role-features wf-role-features-compact">
        <?php foreach ($permissions as $group => $items): ?>
            <section class="wf-role-feature">
                <div class="wf-role-feature-head">
                    <h3><?= e($group) ?></h3>
                    <span><?= count($items) ?></span>
                </div>
                <ul class="wf-role-feature-list">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <span class="wf-role-feature-mark" aria-hidden="true"><?= icon('check-lg') ?></span>
                            <span><strong><?= e($item['name']) ?></strong></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
