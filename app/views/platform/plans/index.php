<?php
$canEdit = can('platform.plans.update');
$oldCodes = old('plan_codes', null);
$usingOld = is_array($oldCodes);
$oldNames = old('plan_names', []);
$oldFeatures = old('plan_features', []);
$oldLimits = old('plan_limits', []);
if (!is_array($oldLimits)) {
    $oldLimits = [];
}
if (!is_array($oldNames)) {
    $oldNames = [];
}
if (!is_array($oldFeatures)) {
    $oldFeatures = [];
}
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2>Plans</h2>
        <p class="text-muted mb-0">Choose the functions each plan includes. A company can still add or remove functions on its own edit page.</p>
    </div>
</div>

<section class="wf-card mb-4">
    <form method="post" action="<?= e(base_url('platform/plans')) ?>">
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table wf-table wf-keep wf-plan-table align-middle">
                <thead>
                <tr>
                    <th>Function</th>
                    <?php foreach ($plans as $code => $features): ?>
                        <?php
                        $name = $usingOld ? (string) ($oldNames[$code] ?? ($names[$code] ?? $code)) : (string) ($names[$code] ?? $code);
                        $used = (int) ($usage[$code] ?? 0);
                        ?>
                        <th>
                            <input type="hidden" name="plan_codes[]" value="<?= e($code) ?>">
                            <label class="visually-hidden" for="plan-name-<?= e($code) ?>"><?= e($code) ?> name</label>
                            <input class="form-control form-control-sm <?= error('name_' . $code) ? 'is-invalid' : '' ?>" id="plan-name-<?= e($code) ?>" name="plan_names[<?= e($code) ?>]" value="<?= e($name) ?>" maxlength="80" <?= $canEdit ? '' : 'disabled' ?>>
                            <label class="wf-plan-limit-label" for="plan-limit-<?= e($code) ?>">Workspace limit</label>
                            <input class="form-control form-control-sm" id="plan-limit-<?= e($code) ?>" type="number" min="1" max="999" name="plan_limits[<?= e($code) ?>]" value="<?= e((string) ($usingOld ? ($oldLimits[$code] ?? '') : ($limits[$code] ?? ''))) ?>" placeholder="Unlimited" <?= $canEdit ? '' : 'disabled' ?>>
                            <span class="wf-feature-tag">Blank means unlimited</span>
                            <?php if ($used > 0): ?>
                                <span class="wf-feature-tag"><?= (int) $used ?> <?= $used === 1 ? 'company' : 'companies' ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($catalog as $slug => $feature): ?>
                    <tr>
                        <th>
                            <?= e($feature['label']) ?>
                            <small class="d-block fw-normal text-muted"><?= e($feature['detail']) ?></small>
                        </th>
                        <?php foreach ($plans as $code => $features): ?>
                            <?php
                            $checked = $usingOld
                                ? (isset($oldFeatures[$code]) && is_array($oldFeatures[$code]) && in_array($slug, $oldFeatures[$code], true))
                                : in_array($slug, $features, true);
                            ?>
                            <td>
                                <input class="form-check-input" type="checkbox" name="plan_features[<?= e($code) ?>][]" value="<?= e($slug) ?>" aria-label="<?= e($feature['label'] . ' on ' . $code) ?>" <?= $checked ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canEdit): ?>
            <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save plans</button>
        <?php endif; ?>
    </form>
</section>

<?php
$removable = [];
foreach ($plans as $code => $features) {
    if ($code !== 'FREE' && (int) ($usage[$code] ?? 0) === 0) {
        $removable[$code] = (string) ($names[$code] ?? $code);
    }
}
?>
<?php if ($canEdit && $removable !== []): ?>
<section class="wf-card mb-4">
    <div class="wf-card-head">
        <h3>Remove an unused plan</h3>
    </div>
    <form method="post" action="<?= e(base_url('platform/plans/delete')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="remove-plan">Plan</label>
                <select class="form-select" id="remove-plan" name="code">
                    <?php foreach ($removable as $code => $label): ?>
                        <option value="<?= e($code) ?>"><?= e($label) ?> (<?= e($code) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-soft-danger" type="submit" data-confirm="Are you sure you want to remove this plan?"><?= icon('trash3') ?> Remove plan</button>
            </div>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($canEdit): ?>
<section class="wf-card">
    <div class="wf-card-head">
        <h3>Add a plan</h3>
    </div>
    <form method="post" action="<?= e(base_url('platform/plans/create')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="plan-code">Code</label>
                <input class="form-control <?= error('code') ? 'is-invalid' : '' ?>" id="plan-code" name="code" value="<?= e((string) old('code')) ?>" maxlength="40" placeholder="GROWTH" required>
                <?php if ($message = error('code')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="plan-title">Name</label>
                <input class="form-control" id="plan-title" name="name" value="<?= e((string) old('name')) ?>" maxlength="80" placeholder="Growth" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="plan-workspace-limit">Workspace limit</label>
                <input class="form-control" id="plan-workspace-limit" type="number" min="1" max="999" name="workspace_limit" value="<?= e((string) old('workspace_limit')) ?>" placeholder="Same as start plan">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="copy-from">Start from</label>
                <select class="form-select" id="copy-from" name="copy_from">
                    <?php foreach ($names as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= selected(old('copy_from', 'FREE'), $code) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-outline w-100" type="submit"><?= icon('plus-lg') ?> Add</button>
            </div>
        </div>
    </form>
</section>
<?php endif; ?>
