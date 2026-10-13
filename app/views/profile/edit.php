<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Account</p>
        <h2>My profile</h2>
        <p class="text-muted mb-0">Your personal details for this workspace.</p>
    </div>
</div>

<section class="wf-card">
    <form method="post" enctype="multipart/form-data" action="<?= e(base_url('profile')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $user->name)) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input class="form-control" value="<?= e($user->email) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $user->phone)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="profile_photo">Profile photo</label>
                <input class="form-control" id="profile_photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-12"><hr></div>
            <div class="col-md-4">
                <label class="form-label" for="current_password">Current password</label>
                <input class="form-control" id="current_password" type="password" name="current_password">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password">New password</label>
                <input class="form-control" id="password" type="password" name="password" minlength="<?= (int) $minPassword ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input class="form-control" id="password_confirmation" type="password" name="password_confirmation">
            </div>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save profile</button>
        </div>
    </form>
</section>
