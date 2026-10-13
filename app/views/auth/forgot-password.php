<h1>Reset password</h1>
<p class="wf-lead">Enter your email and we will send reset instructions if an account exists.</p>

<form method="post" action="<?= e(base_url('forgot-password')) ?>" class="wf-form">
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email')) ?>" required>
    </div>
    <button class="btn btn-primary w-100" type="submit"><?= icon('envelope') ?> Send reset link</button>
</form>

<p class="wf-auth-footer">
    <a href="<?= e(base_url('login')) ?>">Back to sign in</a>
</p>
