<?php
$headerUser = auth_user();
$noteCount = 0;
$notes = [];
if ($headerUser && $headerUser->organization_id) {
    $noteCount = \App\Models\Notification::unreadCount((int) $headerUser->organization_id, (int) $headerUser->id);
    $notes = \App\Models\Notification::forUser((int) $headerUser->organization_id, (int) $headerUser->id, 6);
}
?>
<header class="wf-topbar">
    <button class="wf-icon-btn wf-menu-btn" type="button" id="wf-sidebar-toggle" aria-label="Toggle sidebar">
        <?= icon('list') ?>
    </button>
    <button class="wf-search-btn" type="button" id="wf-search-open" aria-label="Search">
        <?= icon('search') ?>
        <span>Search</span>
        <kbd>Ctrl K</kbd>
    </button>
    <div class="wf-topbar-title">
        <h1><?= e($title ?? '') ?></h1>
    </div>
    <div class="wf-topbar-actions">
        <?php if (can('time.view') && feature_enabled('time') && $headerUser && $headerUser->organization_id): ?>
            <?php $runningTimer = \App\Models\TimeEntry::runningFor((int) $headerUser->organization_id, (int) $headerUser->id); ?>
            <?php if ($runningTimer): ?>
                <form method="post" action="<?= e(base_url('time/stop')) ?>" class="wf-timer">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return" value="time">
                    <span>Timer · <?= e($runningTimer->task_title ?: $runningTimer->project_name) ?><?= $runningTimer->source === 'auto' ? ' · auto' : '' ?></span>
                    <button class="btn btn-primary btn-sm" type="submit"><?= icon('stop-circle') ?> Stop</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <div class="dropdown" data-bs-display="static">
            <button class="wf-icon-btn wf-note-link" type="button" data-bs-toggle="dropdown" aria-label="<?= (int) $noteCount ?> unread notifications">
                <?= icon('bell') ?>
                <?php if ($noteCount > 0): ?><span class="wf-note-count"><?= (int) $noteCount ?></span><?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end wf-note-menu">
                <div class="wf-note-head">
                    <strong>Notifications</strong>
                    <?php if ($noteCount > 0): ?><span><?= (int) $noteCount ?> unread</span><?php endif; ?>
                </div>
                <?php if ($notes === []): ?>
                    <p class="wf-note-empty">No notifications yet.</p>
                <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                        <?php
                        $notePath = can('portal.view')
                            ? 'portal/notifications/' . $note->id
                            : 'notifications/' . $note->id;
                        ?>
                        <a class="wf-note-item <?= $note->isUnread() ? 'is-unread' : '' ?>" href="<?= e(base_url($notePath)) ?>">
                            <strong><?= e($note->title) ?></strong>
                            <?php if ($note->body): ?><small><?= e($note->body) ?></small><?php endif; ?>
                            <span><?= e(format_datetime($note->created_at)) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (can('portal.view')): ?>
                    <a class="wf-note-all" href="<?= e(base_url('portal/notifications')) ?>">All notifications</a>
                <?php else: ?>
                    <form method="post" action="<?= e(base_url('notifications/read')) ?>" class="wf-note-all-form">
                        <?= csrf_field() ?>
                        <button class="wf-note-all" type="submit"><?= icon('check2-all') ?> Mark all read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="dropdown">
            <button class="wf-account-btn" type="button" data-bs-toggle="dropdown" aria-label="Account menu">
                <span class="wf-account-copy">
                    <strong><?= e($headerUser?->name ?? 'User') ?></strong>
                    <small><?= e($headerUser?->role()?->name ?? '') ?></small>
                </span>
                <span class="wf-avatar-btn" aria-hidden="true">
                    <?php if (!empty($headerUser?->profile_photo)): ?>
                        <img src="<?= e(asset($headerUser->profile_photo)) ?>" alt="">
                    <?php else: ?>
                        <span><?= e(user_initials($headerUser?->name ?? 'U')) ?></span>
                    <?php endif; ?>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end wf-account-menu">
                <li class="wf-account-id">
                    <strong><?= e($headerUser?->name ?? 'User') ?></strong>
                    <span><?= e($headerUser?->role()?->name ?? '') ?></span>
                </li>
                <li><a class="dropdown-item" href="<?= e(base_url('profile')) ?>"><?= icon('person') ?> Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(base_url('settings')) ?>"><?= icon('gear') ?> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(base_url('logout')) ?>" data-confirm="Are you sure you want to sign out?">
                        <?= csrf_field() ?>
                        <button class="dropdown-item" type="submit"><?= icon('box-arrow-right') ?> Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
