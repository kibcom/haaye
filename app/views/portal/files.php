<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Portal</p>
        <h2>Files</h2>
        <p class="text-muted mb-0">Files shared on deliverables for your company.</p>
    </div>
</div>
<section class="wf-card">
    <?php if ($files === []): ?>
        <?php $title = 'No files yet'; $message = 'Shared files will appear here.'; include BASE_PATH . '/app/views/components/empty-state.php'; ?>
    <?php else: ?>
        <ul class="wf-file-list">
            <?php foreach ($files as $file): ?>
                <li>
                    <a href="<?= e(base_url('deliverables/' . $file->id . '/file')) ?>"><?= e($file->original_name ?: $file->name) ?></a>
                    <div class="text-muted small"><?= e($file->name) ?> · <?= e($file->project_name) ?> · <?= e(format_date($file->updated_at)) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
