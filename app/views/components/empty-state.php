<div class="wf-empty">
    <h3><?= e($title ?? 'Nothing here yet') ?></h3>
    <p><?= e($message ?? 'When records are added, they will appear here.') ?></p>
    <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
        <a class="btn btn-primary" href="<?= e(base_url($actionUrl)) ?>"><?= icon('plus-lg') ?> <?= e($actionLabel) ?></a>
    <?php endif; ?>
</div>
