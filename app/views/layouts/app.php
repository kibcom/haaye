<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Dashboard') ?> · <?= e(app_name()) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e(asset('images/favicon.png')) ?>">
    <?php \App\Core\View::component('theme-boot', []); ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body data-home="<?= e(base_url(is_super_admin() ? 'platform' : 'dashboard')) ?>">
<div class="wf-app">
    <?php \App\Core\View::component('sidebar', []); ?>
    <div class="wf-main">
        <?php \App\Core\View::component('navbar', ['title' => $title ?? '']); ?>
        <main class="wf-content">
            <?php \App\Core\View::component('alerts', ['alert' => $alert ?? null]); ?>
            <div class="wf-trail">
                <button class="wf-back-btn" type="button" id="wf-back" aria-label="Back">
                    <?= icon('arrow-left') ?>
                    <span>Back</span>
                </button>
                <?php if (!empty($breadcrumbs)): ?>
                    <nav class="wf-breadcrumb" aria-label="Breadcrumb">
                        <?php foreach ($breadcrumbs as $index => $crumb): ?>
                            <?php if ($index > 0): ?><span class="wf-breadcrumb-sep">/</span><?php endif; ?>
                            <?php if (!empty($crumb['url'])): ?>
                                <a href="<?= e(base_url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a>
                            <?php else: ?>
                                <span><?= e($crumb['label']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </div>
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<div class="wf-confirm" id="wf-confirm" data-tone="neutral" hidden>
    <div class="wf-confirm-panel" role="dialog" aria-modal="true" aria-labelledby="wf-confirm-title">
        <span class="wf-confirm-mark" aria-hidden="true"></span>
        <h2 id="wf-confirm-title">Are you sure?</h2>
        <p id="wf-confirm-text"></p>
        <div class="wf-confirm-actions">
            <button class="btn" type="button" id="wf-confirm-cancel">Cancel</button>
            <button class="btn" type="button" id="wf-confirm-ok">Yes</button>
        </div>
    </div>
</div>
<div class="wf-command" id="wf-command" hidden>
    <div class="wf-command-panel" role="dialog" aria-modal="true" aria-label="Search">
        <input id="wf-command-input" type="search" placeholder="Jump to a page or search records" autocomplete="off">
        <ul class="wf-command-list" id="wf-command-list"></ul>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('js/app.php')) ?>?v=<?= (int) (@filemtime(public_path('js/app.dat')) ?: time()) ?>"></script>
</body>
</html>
