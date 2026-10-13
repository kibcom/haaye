<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Portal</p>
        <h2>Notifications</h2>
    </div>
    <?php if ($notifications !== []): ?>
        <form method="post" action="<?= e(base_url('portal/notifications/read')) ?>" data-confirm="Are you sure you want to mark every notification as read?">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit"><?= icon('check2-all') ?> Mark all read</button>
        </form>
    <?php endif; ?>
</div>
<section class="wf-card">
    <?php if ($notifications === []): ?>
        <?php $title = 'No notifications'; $message = 'Approvals, invoices, payments, and shared files will show up here.'; include BASE_PATH . '/app/views/components/empty-state.php'; ?>
    <?php else: ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($notifications as $note): ?>
                <li class="mb-3">
                    <a href="<?= e(base_url('portal/notifications/' . $note->id)) ?>"><?= e($note->title) ?></a>
                    <?php if ($note->isUnread()): ?><span class="badge text-bg-info wf-badge">Unread</span><?php endif; ?>
                    <?php if ($note->body): ?><div><?= e($note->body) ?></div><?php endif; ?>
                    <div class="text-muted small"><?= e(format_datetime($note->created_at)) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
