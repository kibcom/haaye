<?php $editing = $user !== null; ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform users</p>
        <h2><?= $editing ? 'Edit platform user' : 'Add platform user' ?></h2>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($editing ? 'platform/users/' . $user->id : 'platform/users')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $user->name ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email', $user->email ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" <?= selected(old('status', $user->status ?? 'active'), 'active') ?>>Active</option>
                    <option value="inactive" <?= selected(old('status', $user->status ?? 'active'), 'inactive') ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password"><?= $editing ? 'New password (optional)' : 'Password' ?></label>
                <input class="form-control" id="password" type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="<?= (int) $minPassword ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" <?= $editing ? '' : 'required' ?>>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save</button>
            <a class="btn btn-secondary" href="<?= e(base_url('platform/users')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
