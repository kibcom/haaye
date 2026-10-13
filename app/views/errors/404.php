<h1>Page not found</h1>
<p class="wf-lead"><?= e($message ?? 'The page you requested could not be found.') ?></p>
<a class="btn btn-primary" href="<?= e(base_url('/')) ?>"><?= icon('house') ?> Go home</a>
