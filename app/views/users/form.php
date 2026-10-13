<?php $editing = $user !== null; ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">People</p>
        <h2><?= $editing ? 'Edit person' : 'Add person' ?></h2>
        <p class="text-muted mb-0"><?= $editing ? 'Update access and profile details.' : 'Invite someone into this organization.' ?></p>
    </div>
</div>

<section class="wf-card">
    <form method="post" enctype="multipart/form-data" action="<?= e(base_url($editing ? 'users/' . $user->id : 'users')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Full name</label>
                <input class="form-control <?= has_error('name') ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e((string) old('name', $user->name ?? '')) ?>" required>
                <?php if (error('name')): ?><div class="invalid-feedback"><?= e((string) error('name')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>" id="email" type="email" name="email" value="<?= e((string) old('email', $user->email ?? '')) ?>" required>
                <?php if (error('email')): ?><div class="invalid-feedback"><?= e((string) error('email')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $user->phone ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="job_title">Job title</label>
                <input class="form-control" id="job_title" name="job_title" value="<?= e((string) old('job_title', $user->job_title ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="employee_code">Employee ID</label>
                <input class="form-control" id="employee_code" name="employee_code" value="<?= e((string) old('employee_code', $user->employee_code ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="joining_date">Joining date</label>
                <input class="form-control" id="joining_date" type="date" name="joining_date" value="<?= e((string) old('joining_date', $user->joining_date ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="role_id">Role</label>
                <select class="form-select <?= has_error('role_id') ? 'is-invalid' : '' ?>" id="role_id" name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role->id ?>" <?= selected(old('role_id', $user->role_id ?? ''), $role->id) ?>><?= e($role->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (error('role_id')): ?><div class="invalid-feedback"><?= e((string) error('role_id')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" <?= selected(old('status', $user->status ?? 'active'), 'active') ?>>Active</option>
                    <option value="inactive" <?= selected(old('status', $user->status ?? 'active'), 'inactive') ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="profile_photo">Profile photo</label>
                <input class="form-control" id="profile_photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password"><?= $editing ? 'New password (optional)' : 'Password' ?></label>
                <input class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>" id="password" type="password" name="password" <?= $editing ? '' : 'required' ?> minlength="<?= (int) $minPassword ?>">
                <div class="form-text">At least <?= (int) $minPassword ?> characters.</div>
                <?php if (error('password')): ?><div class="invalid-feedback"><?= e((string) error('password')) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control <?= has_error('password') ? 'is-invalid' : '' ?>" id="password_confirmation" type="password" name="password_confirmation" <?= $editing ? '' : 'required' ?>>
            </div>
        </div>
        <?php if (!empty($canEditAccess)): ?>
            <?php \App\Core\View::component('permission-checks', [
                'permissionGroups' => $permissionGroups ?? [],
                'selectedPermissionIds' => $selectedPermissionIds ?? [],
                'customEnabled' => $customEnabled ?? false,
                'canCustomize' => true,
            ]); ?>
            <script type="application/json" id="wf-role-permissions"><?= json_encode($rolePermissionMap ?? new \stdClass(), JSON_UNESCAPED_UNICODE) ?></script>
        <?php endif; ?>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'person-plus') ?> <?= $editing ? 'Save changes' : 'Add person' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url('users')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
