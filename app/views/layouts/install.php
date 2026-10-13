<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Install') ?> · <?= e(app_name()) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('images/favicon.png')) ?>">
    <?php \App\Core\View::component('theme-boot', []); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="wf-auth-body">
<main class="wf-auth-shell wf-install-shell">
    <div class="wf-auth-brand">
        <img class="wf-product-logo" src="<?= e(asset('images/haaye-logo.png')) ?>" alt="<?= e(app_name()) ?>">
        <p>Foundation setup</p>
    </div>
    <div class="wf-auth-card wf-install-card">
        <?php \App\Core\View::component('alerts', ['alert' => $alert ?? null]); ?>
        <?= $content ?? '' ?>
    </div>
</main>
</body>
</html>
