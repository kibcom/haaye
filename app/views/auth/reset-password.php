<h1>Choose a new password</h1>
<p class="wf-lead">Use at least <?= (int) ($minPassword ?? 10) ?> characters.</p>

<form method="post" action="<?= e(base_url('reset-password')) ?>" class="wf-form">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input class="form-control" id="password" type="password" name="password" minlength="<?= (int) ($minPassword ?? 10) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password_confirmation">Confirm password</label>
        <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required>
    </div>
    <button class="btn btn-primary w-100" type="submit"><?= icon('key') ?> Update password</button>
</form>
