<?php
$used = (int) ($allowance['used'] ?? 0);
$limit = $allowance['limit'] ?? null;
$canCreate = !empty($allowance['can_create']);
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Account</p>
        <h2>New workspace</h2>
        <p class="text-muted mb-0">
            <?php if ($limit === null): ?>
                The <?= e($planName) ?> plan does not limit how many workspaces this account can open. You are using <?= (int) $used ?>.
            <?php else: ?>
                The <?= e($planName) ?> plan includes <?= (int) $limit ?> <?= (int) $limit === 1 ? 'workspace' : 'workspaces' ?>. This account is using <?= (int) $used ?> of <?= (int) $limit ?>.
            <?php endif; ?>
        </p>
    </div>
</div>

<section class="wf-card">
    <?php if (!$canCreate): ?>
        <p class="mb-0">This account is at the plan limit. A higher plan can open more workspaces. Switch to a workspace on that plan, or ask the platform admin to raise the workspace number on the plan.</p>
    <?php else: ?>
        <form method="post" action="<?= e(base_url('workspaces')) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Workspace name</label>
                    <input class="form-control" id="name" name="name" value="<?= e((string) old('name')) ?>" maxlength="180" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="industry">Industry</label>
                    <input class="form-control" id="industry" name="industry" value="<?= e((string) old('industry')) ?>" maxlength="120">
                </div>
            </div>
            <p class="text-muted small mt-3 mb-0">You stay the owner, and the same email and password open this workspace. It uses the <?= e($planName) ?> plan.</p>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= icon('plus-lg') ?> Create workspace</button>
                <a class="btn btn-secondary" href="<?= e(base_url('dashboard')) ?>"><?= icon('x-lg') ?> Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>
