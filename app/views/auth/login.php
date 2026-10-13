<h2>Welcome Back</h2>
<p class="wf-login-sub">Sign in to continue to your account</p>

<form method="post" action="<?= e(base_url('login')) ?>" class="wf-form wf-login-form">
    <?= csrf_field() ?>
    <div class="wf-login-field">
        <label class="visually-hidden" for="email">Email</label>
        <?= icon('envelope') ?>
        <input class="form-control <?= has_error('email') ? 'is-invalid' : '' ?>" id="email" type="email" name="email" value="<?= e((string) old('email')) ?>" placeholder="owner@haaye.com" autocomplete="username" required>
    </div>
    <div class="wf-login-field wf-password-field">
        <label class="visually-hidden" for="password">Password</label>
        <?= icon('lock') ?>
        <input class="form-control" id="password" type="password" name="password" placeholder="Password" autocomplete="current-password" required>
        <button class="wf-password-toggle" type="button" aria-label="Show password" aria-pressed="false" aria-controls="password">
            <?= icon('eye') ?>
        </button>
    </div>
    <div class="wf-login-row">
        <label class="wf-login-remember" for="remember">
            <input id="remember" type="checkbox" name="remember" value="1">
            <span>Remember me</span>
        </label>
        <a href="<?= e(base_url('forgot-password')) ?>">Forgot password?</a>
    </div>
    <button class="wf-login-submit" type="submit"><?= icon('arrow-right') ?> Continue</button>
</form>

<p class="wf-login-signup">Don't have an account? <a href="mailto:admin@haaye.com">Contact administrator</a></p>
