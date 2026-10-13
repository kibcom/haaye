<?php
$active = [];
foreach ($projects as $project) {
    if ((int) $project->status_closed !== 1) {
        $active[] = $project;
    }
}
$actionItems = array_values(array_filter(
    $deliverables,
    static function ($item): bool {
        return in_array($item->status, ['client_review', 'changes_requested'], true);
    }
));
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker"><?= e($organization->name ?? 'Portal') ?></p>
        <h2>Your projects</h2>
        <p class="text-muted mb-0">Progress, approvals, invoices, and files for your company.</p>
    </div>
</div>

<?php if ($clientId <= 0): ?>
    <section class="wf-card">
        <p class="mb-0">This login is not linked to a company yet. Ask your account team to connect it.</p>
    </section>
<?php else: ?>
    <div class="row g-2 mb-4">
        <div class="col-6 col-lg-4">
            <a class="wf-stat" href="<?= e(base_url('portal/projects')) ?>">
                <?= icon('folder') ?>
                <span>Active projects</span>
                <strong><?= count($active) ?></strong>
                <small>View projects</small>
            </a>
        </div>
        <div class="col-6 col-lg-4">
            <a class="wf-stat" href="<?= e(base_url('approvals')) ?>">
                <?= icon('check2-circle') ?>
                <span>Pending approvals</span>
                <strong><?= (int) $pendingApprovals ?></strong>
                <small>Review decisions</small>
            </a>
        </div>
        <div class="col-6 col-lg-4">
            <a class="wf-stat" href="<?= e(base_url('portal/invoices')) ?>">
                <?= icon('wallet2') ?>
                <span>Outstanding invoices</span>
                <strong><?= e(money($currency, $outstanding)) ?></strong>
                <small>View invoices</small>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <?php if (!empty($paymentReminders)): ?>
                <section class="wf-card mb-3">
                    <div class="wf-card-head"><h3>Payment reminders</h3></div>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($paymentReminders as $invoice): ?>
                            <?php $payDue = due_reminder($invoice->due_date, $today ?? null); ?>
                            <li class="mb-2">
                                <a href="<?= e(base_url('portal/invoices/' . $invoice->id)) ?>"><?= e($invoice->number) ?></a>
                                <div class="text-muted small">
                                    <?= e(money($invoice->currency ?: $currency, $invoice->balance)) ?>
                                    <?php if ($payDue): ?> · <span class="wf-due is-<?= e($payDue['tone']) ?>"><?= e($payDue['label']) ?></span><?php endif; ?>
                                    · due <?= e(format_date($invoice->due_date)) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
            <section class="wf-card mb-3">
                <div class="wf-card-head"><h3>Progress</h3></div>
                <?php if ($active === []): ?>
                    <p class="text-muted mb-0">No active projects.</p>
                <?php else: ?>
                    <?php foreach ($active as $project): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <a href="<?= e(base_url('portal/projects/' . $project->id)) ?>"><?= e($project->name) ?></a>
                                <?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
            <?php if ($actionItems !== []): ?>
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Needs your action</h3></div>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($actionItems as $item): ?>
                            <li class="mb-2">
                                <a href="<?= e(base_url('deliverables/' . $item->id)) ?>"><?= e($item->name) ?></a>
                                <div class="text-muted small"><?= e($item->project_name) ?> · <?= e(ucwords(str_replace('_', ' ', $item->status))) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
        <div class="col-lg-5">
            <section class="wf-card mb-3">
                <div class="wf-card-head"><h3>Recent files</h3></div>
                <?php if ($files === []): ?>
                    <p class="text-muted mb-0">Files shared with you will appear here.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($files as $file): ?>
                            <li class="mb-2">
                                <a href="<?= e(base_url('deliverables/' . $file->id . '/file')) ?>"><?= e($file->original_name ?: $file->name) ?></a>
                                <div class="text-muted small"><?= e($file->project_name) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <section class="wf-card">
                <div class="wf-card-head">
                    <h3>Notifications</h3>
                    <a href="<?= e(base_url('portal/notifications')) ?>">View all</a>
                </div>
                <?php if ($notifications === []): ?>
                    <p class="text-muted mb-0">You are up to date.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($notifications as $note): ?>
                            <li class="mb-2">
                                <a href="<?= e(base_url('portal/notifications/' . $note->id)) ?>"><?= e($note->title) ?></a>
                                <div class="text-muted small"><?= e(format_datetime($note->created_at)) ?><?= $note->isUnread() ? ' · Unread' : '' ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
