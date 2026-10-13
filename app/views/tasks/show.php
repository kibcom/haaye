<?php
[$completeSlug, $reopenSlug] = task_status_targets($statuses ?? []);
$statusLabel = $task->status_name ?: ucwords(str_replace('_', ' ', (string) $task->status));
$progressPct = (int) ($autoProgress ?? $task->progress);
$nextStatus = $nextStatus ?? null;
$prevStatus = $prevStatus ?? null;
$priority = strtolower((string) $task->priority);
?>
<div class="wf-task-show">
    <header class="wf-task-hero">
        <div class="wf-task-hero-main">
            <div class="wf-task-main <?= (int) $task->status_closed === 1 ? 'is-done' : '' ?>">
                <?php if ($canChangeStatus && $completeSlug && $reopenSlug): ?>
                    <button
                        class="wf-check <?= (int) $task->status_closed === 1 ? 'is-done' : '' ?>"
                        type="button"
                        data-url="<?= e(base_url('tasks/' . $task->id . '/status')) ?>"
                        data-complete="<?= e($completeSlug) ?>"
                        data-reopen="<?= e($reopenSlug) ?>"
                        aria-pressed="<?= (int) $task->status_closed === 1 ? 'true' : 'false' ?>"
                        aria-label="<?= (int) $task->status_closed === 1 ? 'Reopen' : 'Complete' ?> <?= e($task->title) ?>"
                    ><?= icon('check-lg') ?></button>
                <?php endif; ?>
                <div>
                    <h1><?= e($task->title) ?></h1>
                    <p class="wf-task-hero-sub">
                        <a href="<?= e(base_url('projects/' . $task->project_id)) ?>"><?= e($task->project_name ?: 'Project') ?></a>
                        <?php if ($task->client_name): ?>
                            <span aria-hidden="true">·</span> <?= e($task->client_name) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php if ($canManage): ?>
            <div class="wf-task-hero-actions">
                <a class="btn btn-sm btn-soft-primary" href="<?= e(base_url('tasks/' . $task->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
                <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this task?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash3') ?> Delete</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($pendingApprovals)): ?>
        <div class="wf-task-banner" role="status">
            <?= icon('hourglass-split') ?>
            <div>
                <?php foreach ($pendingApprovals as $approval): ?>
                    <p>
                        Waiting for <?= e($approval->approval_kind) ?> approval to move to
                        <strong><?= e(ucwords(str_replace('_', ' ', $approval->to_status))) ?></strong>.
                        <?php if (can('approvals.view')): ?>
                            <a href="<?= e(base_url('approvals/' . $approval->id)) ?>">Review</a>
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <section class="wf-task-summary" aria-label="Task summary">
        <div class="wf-task-summary-item">
            <span>Status</span>
            <strong class="wf-task-chip is-status"><?= e($statusLabel) ?></strong>
        </div>
        <div class="wf-task-summary-item">
            <span>Priority</span>
            <strong class="wf-task-chip is-<?= e($priority) ?>"><?= e(ucfirst($task->priority)) ?></strong>
        </div>
        <div class="wf-task-summary-item">
            <span>Assignee</span>
            <strong><?= e($task->assignee_name ?: 'Unassigned') ?></strong>
        </div>
        <div class="wf-task-summary-item">
            <span>Due</span>
            <strong><?= e(format_date($task->due_date)) ?></strong>
        </div>
        <div class="wf-task-summary-item is-progress">
            <span>Progress</span>
            <?php \App\Core\View::component('progress-meter', ['percent' => $progressPct]); ?>
        </div>
    </section>

    <div class="wf-task-layout">
        <div class="wf-task-maincol">
            <section class="wf-card wf-task-panel">
                <div class="wf-card-head"><h2>About</h2></div>
                <div class="wf-task-facts">
                    <span>Reporter <strong><?= e($task->reporter_name ?: '') ?></strong></span>
                    <span>Start <strong><?= e(format_date($task->start_date)) ?></strong></span>
                    <span>Created <strong><?= e(format_datetime($task->created_at)) ?></strong></span>
                </div>
                <?php if ($task->description): ?>
                    <p class="wf-task-desc"><?= nl2br(e($task->description)) ?></p>
                <?php else: ?>
                    <p class="wf-task-desc is-empty">No description yet.</p>
                <?php endif; ?>
            </section>

            <?php if (!empty($canStartWork) || !empty($canSubmitWork) || !empty($pipelineStage) || !empty($task->completion_criteria) || !empty($task->submission_note)): ?>
                <section class="wf-card wf-task-panel">
                    <div class="wf-card-head"><h2>Work &amp; approval</h2></div>
                    <?php if (!empty($pipelineStage)): ?>
                        <p class="small mb-2">
                            Pipeline stage: <strong><?= e($pipelineStage->name) ?></strong>
                            <?php if ($task->workflow_run_id): ?>
                                · <a href="<?= e(base_url('pipeline/runs/' . $task->workflow_run_id)) ?>">Open run</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($task->completion_criteria)): ?>
                        <p class="mb-2"><strong>Required deliverables</strong><br><?= nl2br(e((string) $task->completion_criteria)) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($task->submission_note)): ?>
                        <p class="mb-2 text-muted"><strong>Last submission note</strong><br><?= nl2br(e((string) $task->submission_note)) ?></p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <?php if (!empty($canStartWork)): ?>
                            <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/start')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-primary" type="submit"><?= icon('play-fill') ?> Start work</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($canSubmitWork)): ?>
                            <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/submit')) ?>" class="flex-grow-1" style="min-width:220px" data-confirm="Submit for approval? This does not approve the work.">
                                <?= csrf_field() ?>
                                <label class="form-label" for="submission_note">Submission note (optional)</label>
                                <textarea class="form-control form-control-sm mb-2" id="submission_note" name="submission_note" rows="2" maxlength="2000" placeholder="What did you deliver?"></textarea>
                                <button class="btn btn-sm btn-aqua" type="submit"><?= icon('send') ?> Submit for approval</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small mb-0 mt-2">Submitting requests review. Only an authorized approver can approve — not the assignee.</p>
                </section>
            <?php endif; ?>

            <?php if ($canChangeStatus): ?>
                <section class="wf-card wf-task-panel">
                    <div class="wf-card-head">
                        <h2>Update progress</h2>
                        <span class="wf-task-panel-meta"><?= (int) $progressPct ?>%</span>
                    </div>
                    <div class="wf-status-board">
                        <div class="wf-status-board-now">
                            <div>
                                <span class="wf-progress-label">Current</span>
                                <strong><?= e($statusLabel) ?></strong>
                            </div>
                            <?php \App\Core\View::component('progress-meter', ['percent' => $progressPct]); ?>
                        </div>
                        <div class="wf-progress-actions">
                            <?php if ($prevStatus): ?>
                                <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/status')) ?>" data-confirm="Move this task back to <?= e($prevStatus->name) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="<?= e($prevStatus->slug) ?>">
                                    <button class="btn btn-sm btn-secondary" type="submit"><?= icon('arrow-left') ?> Back to <?= e($prevStatus->name) ?></button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" type="button" disabled><?= icon('arrow-left') ?> Back</button>
                            <?php endif; ?>
                            <?php if ($nextStatus): ?>
                                <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/status')) ?>" data-confirm="Advance this task to <?= e($nextStatus->name) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="<?= e($nextStatus->slug) ?>">
                                    <button class="btn btn-sm btn-primary" type="submit">Advance to <?= e($nextStatus->name) ?> <?= icon('arrow-right') ?></button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-primary" type="button" disabled>Advance <?= icon('arrow-right') ?></button>
                            <?php endif; ?>
                        </div>
                        <p class="wf-progress-hint mb-0">Progress updates automatically when the status moves.</p>
                        <details class="wf-status-advanced">
                            <summary>Advanced · pick any status</summary>
                            <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/status')) ?>" class="wf-status-advanced-form" data-confirm="Change this task status?">
                                <?= csrf_field() ?>
                                <label class="form-label" for="task-status-advanced">Status</label>
                                <div class="wf-status-advanced-row">
                                    <select class="form-select form-select-sm" id="task-status-advanced" name="status">
                                        <?php foreach ($statuses as $item): ?>
                                            <option value="<?= e($item->slug) ?>" <?= selected($task->status, $item->slug) ?>><?= e($item->name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-soft-primary" type="submit"><?= icon('check2') ?> Set status</button>
                                </div>
                            </form>
                        </details>
                    </div>
                </section>
            <?php endif; ?>

            <section class="wf-card wf-task-panel">
                <div class="wf-card-head"><h2>Comments</h2></div>
                <?php if (empty($comments)): ?>
                    <p class="text-muted mb-0">No comments yet.</p>
                <?php else: ?>
                    <ul class="wf-task-comments">
                        <?php foreach ($comments as $comment): ?>
                            <li>
                                <div class="wf-task-comment-head">
                                    <strong><?= e($comment->user_name ?: 'Former user') ?></strong>
                                    <time><?= e(format_datetime($comment->created_at)) ?></time>
                                </div>
                                <div class="wf-task-comment-body"><?= nl2br(e($comment->body)) ?></div>
                                <?php foreach ($filesByComment[(int) $comment->id] ?? [] as $file): ?>
                                    <div class="wf-task-comment-file">
                                        <a href="<?= e(base_url('tasks/' . $task->id . '/attachments/' . $file->id)) ?>"><?= e($file->original_name) ?></a>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (\App\Services\TaskAccess::canDeleteComment($task, $comment->user_id)): ?>
                                    <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/comments/' . $comment->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this comment?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($canComment): ?>
                    <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/comments')) ?>" enctype="multipart/form-data" class="wf-task-composer">
                        <?= csrf_field() ?>
                        <label class="form-label" for="body">Add a comment</label>
                        <textarea class="form-control" id="body" name="body" rows="2" maxlength="4000" placeholder="Write a short update…"></textarea>
                        <div class="wf-task-composer-actions">
                            <?php if ($canUpload): ?>
                                <input class="form-control form-control-sm" type="file" name="attachment" aria-label="Attach a file to this comment">
                            <?php endif; ?>
                            <button class="btn btn-sm btn-primary" type="submit"><?= icon('chat') ?> Comment</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <aside class="wf-task-sidecol">
            <?php if (can('time.view') && feature_enabled('time')): ?>
                <section class="wf-card wf-task-panel">
                    <div class="wf-card-head">
                        <h2>Time</h2>
                        <span class="wf-task-panel-meta"><?= e(format_minutes((int) ($timeMinutes ?? 0))) ?></span>
                    </div>
                    <?php if (!empty($autoTracking) && $runningTimer && (int) $runningTimer->task_id === (int) $task->id): ?>
                        <p class="wf-task-auto-time mb-2"><?= icon('stopwatch') ?> Auto-tracking time on this task</p>
                    <?php endif; ?>
                    <?php if (can('time.create')): ?>
                        <div class="wf-task-side-actions">
                            <?php if ($runningTimer && (int) $runningTimer->task_id === (int) $task->id): ?>
                                <form method="post" action="<?= e(base_url('time/stop')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return" value="tasks/<?= (int) $task->id ?>">
                                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('stop-circle') ?> Stop timer</button>
                                </form>
                            <?php elseif ($runningTimer): ?>
                                <p class="text-muted small mb-0">Timer running on <?= e($runningTimer->task_title ?: $runningTimer->project_name) ?>.</p>
                            <?php else: ?>
                                <form method="post" action="<?= e(base_url('time/start')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="project_id" value="<?= (int) $task->project_id ?>">
                                    <input type="hidden" name="task_id" value="<?= (int) $task->id ?>">
                                    <input type="hidden" name="billable" value="1">
                                    <input type="hidden" name="return" value="tasks/<?= (int) $task->id ?>">
                                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('play-circle') ?> Start timer</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline" href="<?= e(base_url('time/log?project=' . $task->project_id . '&task=' . $task->id)) ?>"><?= icon('clock') ?> Log time</a>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($timeEntries)): ?>
                        <p class="text-muted small mb-0">No time logged yet.</p>
                    <?php else: ?>
                        <ul class="wf-task-side-list">
                            <?php foreach ($timeEntries as $timeEntry): ?>
                                <li>
                                    <strong><?= $timeEntry->isRunning() ? 'Running' : e(format_minutes((int) $timeEntry->duration_minutes)) ?></strong>
                                    <span><?= e($timeEntry->user_name) ?> · <?= e(format_date($timeEntry->started_at)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="wf-card wf-task-panel">
                <div class="wf-card-head"><h2>Files</h2></div>
                <?php if (empty($taskFiles)): ?>
                    <p class="text-muted small mb-0">No files yet.</p>
                <?php else: ?>
                    <ul class="wf-task-side-list">
                        <?php foreach ($taskFiles as $file): ?>
                            <li>
                                <a href="<?= e(base_url('tasks/' . $task->id . '/attachments/' . $file->id)) ?>"><?= e($file->original_name) ?></a>
                                <span><?= e($file->user_name ?: 'Former user') ?> · <?= number_format(max(1, (int) round($file->size_bytes / 1024))) ?> KB</span>
                                <?php if (\App\Services\TaskAccess::canDeleteAttachment($task, $file->user_id)): ?>
                                    <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/attachments/' . $file->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this file?">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($canUpload): ?>
                    <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/attachments')) ?>" enctype="multipart/form-data" class="wf-task-upload">
                        <?= csrf_field() ?>
                        <input class="form-control form-control-sm" id="attachment" type="file" name="attachment" required aria-label="Add a file">
                        <button class="btn btn-sm btn-outline" type="submit"><?= icon('upload') ?> Upload</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="wf-card wf-task-panel">
                <div class="wf-card-head"><h2>Activity</h2></div>
                <?php if (empty($activity)): ?>
                    <p class="text-muted small mb-0">Updates will appear here.</p>
                <?php else: ?>
                    <ul class="wf-task-activity">
                        <?php foreach ($activity as $item): ?>
                            <li>
                                <strong><?= e($item->actor_name ?: 'System') ?></strong>
                                <span><?= e($item->description) ?></span>
                                <time><?= e(format_datetime($item->created_at)) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>
