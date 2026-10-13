<h1>Install <?= e(app_name()) ?></h1>
<p class="wf-lead">Phase 1 foundation setup. This creates the database, system roles, and your Super Admin account.</p>

<form method="post" action="<?= e(base_url('install')) ?>">
    <?= csrf_field() ?>
    <h2 class="wf-group-title">Application</h2>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="app_url">Application URL</label>
            <input class="form-control" id="app_url" name="app_url" value="<?= e((string) old('app_url', $detectedUrl ?? 'http://localhost:8080')) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="app_timezone">Timezone</label>
            <input class="form-control" id="app_timezone" name="app_timezone" value="<?= e((string) old('app_timezone', 'UTC')) ?>" required>
        </div>
    </div>

    <h2 class="wf-group-title">Database</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="db_host">Host</label>
            <input class="form-control" id="db_host" name="db_host" value="<?= e((string) old('db_host', '127.0.0.1')) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="db_port">Port</label>
            <input class="form-control" id="db_port" name="db_port" value="<?= e((string) old('db_port', '3306')) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="db_database">Database name</label>
            <input class="form-control" id="db_database" name="db_database" value="<?= e((string) old('db_database', 'workflowpro')) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="db_username">Username</label>
            <input class="form-control" id="db_username" name="db_username" value="<?= e((string) old('db_username', 'root')) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="db_password">Password</label>
            <input class="form-control" id="db_password" type="password" name="db_password" value="<?= e((string) old('db_password', '')) ?>">
        </div>
    </div>

    <h2 class="wf-group-title">Super Admin</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="admin_name">Name</label>
            <input class="form-control" id="admin_name" name="admin_name" value="<?= e((string) old('admin_name', 'Platform Admin')) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="admin_email">Email</label>
            <input class="form-control" id="admin_email" type="email" name="admin_email" value="<?= e((string) old('admin_email')) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="admin_password">Password</label>
            <input class="form-control" id="admin_password" type="password" name="admin_password" minlength="10" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="admin_password_confirmation">Confirm password</label>
            <input class="form-control" id="admin_password_confirmation" type="password" name="admin_password_confirmation" required>
        </div>
    </div>

    <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" value="1" id="with_demo" name="with_demo">
        <label class="form-check-label" for="with_demo">Install demo organization and users (development only)</label>
    </div>

    <button class="btn btn-primary w-100 mt-4" type="submit"><?= icon('download') ?> Install</button>
</form>
