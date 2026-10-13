<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Sign in') ?> · <?= e(app_name()) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('images/favicon.png')) ?>">
    <?php \App\Core\View::component('theme-boot', []); ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="wf-login-body">
<div class="wf-login">
    <section class="wf-login-hero">
        <div class="wf-login-hero-copy">
            <div class="wf-login-brand">
                <img class="wf-login-mark" src="<?= e(asset('images/haaye-hero-mark.png')) ?>" alt="">
                <div class="wf-login-brand-text">
                    <strong>Haaye <span>Manager</span></strong>
                    <small>Plan · Track · Achieve</small>
                </div>
            </div>
            <h1>Manage Your Work, <span>Get More Done</span></h1>
            <p class="wf-login-lead">Haaye Manager is a simple and powerful task management system designed to help you and your team stay organized, focused and productive.</p>
            <ul class="wf-login-points">
                <li>
                    <span class="wf-login-ico"><?= icon('check2-circle') ?></span>
                    <span class="wf-login-copy"><strong>Tasks</strong><span>Track &amp; complete your tasks</span></span>
                </li>
                <li>
                    <span class="wf-login-ico"><?= icon('people') ?></span>
                    <span class="wf-login-copy"><strong>Team</strong><span>Work together easily</span></span>
                </li>
                <li>
                    <span class="wf-login-ico"><?= icon('calendar3') ?></span>
                    <span class="wf-login-copy"><strong>Deadlines</strong><span>Never miss important dates</span></span>
                </li>
                <li>
                    <span class="wf-login-ico"><?= icon('bar-chart') ?></span>
                    <span class="wf-login-copy"><strong>Progress</strong><span>See your achievements</span></span>
                </li>
            </ul>
        </div>
        <img class="wf-login-scene" src="<?= e(asset('images/haaye-login-desk.jpg')) ?>" alt="">
    </section>
    <section class="wf-login-panel">
        <div class="wf-login-card">
            <img class="wf-login-card-logo" src="<?= e(asset('images/haaye-logo.png')) ?>" alt="">
            <p class="wf-login-card-kicker">Simple ERP · Task Management</p>
            <?php \App\Core\View::component('alerts', ['alert' => $alert ?? null]); ?>
            <?= $content ?? '' ?>
        </div>
    </section>
</div>
<script src="<?= e(base_url('js/app.php')) ?>?v=<?= (int) (@filemtime(public_path('js/app.dat')) ?: time()) ?>"></script>
</body>
</html>
