<h1>Access denied</h1>
<p class="wf-lead"><?= e($message ?? 'You do not have permission to access this page.') ?></p>
<a class="btn btn-primary" href="<?= e(base_url('/')) ?>"><?= icon('house') ?> Go home</a>
