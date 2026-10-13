<?php
$basePath = $mine ? 'tasks/mine' : 'tasks';
$query = [
    'q' => $search ?? '',
    'project_id' => $projectId ?? '',
    'status' => $status ?? '',
    'priority' => $priority ?? '',
    'assignee_id' => $assigneeId ?? '',
];
$listQuery = $query;
$listQuery['view'] = 'list';
$boardQuery = $query;
$boardQuery['view'] = 'board';
[$completeSlug, $reopenSlug] = task_status_targets($statuses);
$grouped = [];
if (($viewMode ?? 'list') === 'board') {
    foreach ($statuses as $column) {
        $grouped[$column->slug] = [];
    }
    foreach ($tasks as $task) {
        $grouped[$task->status][] = $task;
    }
}
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Delivery</p>
        <h2><?= $mine ? 'My tasks' : 'Tasks' ?></h2>
        <p class="text-muted mb-0"><?= $mine ? 'Work assigned to you.' : 'Tasks across the projects you can see.' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline" href="<?= e(base_url($mine ? 'tasks' : 'tasks/mine')) ?>"><?= icon($mine ? 'list-check' : 'person-check') ?> <?= $mine ? 'All tasks' : 'My tasks' ?></a>
        <?php if (can('tasks.statuses.manage')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('tasks/statuses')) ?>"><?= icon('sliders') ?> Statuses</a>
        <?php endif; ?>
        <?php if (can('tasks.create')): ?>
            <a class="btn btn-aqua" href="<?= e(base_url('tasks/create')) ?>"><?= icon('plus-lg') ?> New task</a>
        <?php endif; ?>
    </div>
</div>

<form class="wf-filters" method="get" action="<?= e(base_url($basePath)) ?>">
    <input type="hidden" name="view" value="<?= e($viewMode) ?>">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search tasks">
    <select class="form-select" name="project_id">
        <option value="">All projects</option>
        <?php foreach ($projects as $project): ?>
            <option value="<?= (int) $project->id ?>" <?= selected($projectId ?? '', $project->id) ?>><?= e($project->name) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $item): ?>
            <option value="<?= e($item->slug) ?>" <?= selected($status ?? '', $item->slug) ?>><?= e($item->name) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="priority">
        <option value="">All priorities</option>
        <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= selected($priority ?? '', $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!$mine): ?>
        <select class="form-select" name="assignee_id">
            <option value="">All assignees</option>
            <?php foreach ($people as $person): ?>
                <option value="<?= (int) $person->id ?>" <?= selected($assigneeId ?? '', $person->id) ?>><?= e($person->name) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</form>

<div class="wf-view-toggle btn-group mb-3">
    <a class="btn btn-sm <?= $viewMode === 'list' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url($basePath . '?' . http_build_query($listQuery))) ?>">List</a>
    <a class="btn btn-sm <?= $viewMode === 'board' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url($basePath . '?' . http_build_query($boardQuery))) ?>">Board</a>
</div>

<?php if ($viewMode === 'board'): ?>
    <?php if (count($tasks) >= 300): ?>
        <p class="text-muted">Showing the 300 most recently updated tasks. Use filters to narrow the board.</p>
    <?php endif; ?>
    <div class="wf-kanban">
        <?php foreach ($statuses as $column): ?>
            <section class="wf-kanban-col" data-status="<?= e($column->slug) ?>">
                <header>
                    <span><?= e($column->name) ?></span>
                    <small><?= count($grouped[$column->slug] ?? []) ?></small>
                </header>
                <div class="wf-kanban-cards">
                    <?php foreach ($grouped[$column->slug] ?? [] as $task): ?>
                        <?php $canMove = \App\Services\TaskAccess::canChangeStatus($task); ?>
                        <article class="wf-kanban-card" <?= $canMove ? 'draggable="true"' : '' ?> data-task-id="<?= (int) $task->id ?>" data-url="<?= e(base_url('tasks/' . $task->id . '/status')) ?>">
                            <div class="wf-task-main <?= (int) $task->status_closed === 1 ? 'is-done' : '' ?>">
                                <?php if ($canMove && $completeSlug && $reopenSlug): ?>
                                    <button class="wf-check <?= (int) $task->status_closed === 1 ? 'is-done' : '' ?>" type="button" data-url="<?= e(base_url('tasks/' . $task->id . '/status')) ?>" data-complete="<?= e($completeSlug) ?>" data-reopen="<?= e($reopenSlug) ?>" aria-pressed="<?= (int) $task->status_closed === 1 ? 'true' : 'false' ?>" aria-label="<?= (int) $task->status_closed === 1 ? 'Reopen' : 'Complete' ?> <?= e($task->title) ?>"><?= icon('check-lg') ?></button>
                                <?php endif; ?>
                                <a href="<?= e(base_url('tasks/' . $task->id)) ?>"><?= e($task->title) ?></a>
                            </div>
                            <div class="wf-task-meta">
                                <span><?= icon('folder') ?> <?= e($task->project_name ?: 'Project') ?></span>
                                <span><?= icon('person') ?> <?= e($task->assignee_name ?: 'Unassigned') ?></span>
                                <span><?= icon('calendar') ?> <?= e(format_date($task->due_date)) ?></span>
                                <?php \App\Core\View::component('status-badge', ['status' => $task->priority]); ?>
                            </div>
                            <?php if ($canMove): ?>
                                <form method="post" action="<?= e(base_url('tasks/' . $task->id . '/status')) ?>" class="mt-2" data-confirm="Are you sure you want to change this status?">
                                    <?= csrf_field() ?>
                                    <label class="visually-hidden" for="move-<?= (int) $task->id ?>">Move <?= e($task->title) ?></label>
                                    <select class="form-select form-select-sm" id="move-<?= (int) $task->id ?>" name="status" onfocus="this.setAttribute('data-prev', this.value)" onchange="this.form.requestSubmit()">
                                        <?php foreach ($statuses as $item): ?>
                                            <option value="<?= e($item->slug) ?>" <?= selected($task->status, $item->slug) ?>><?= e($item->name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <section class="wf-card">
        <?php if (empty($tasks)): ?>
            <?php \App\Core\View::component('empty-state', [
                'title' => $mine ? 'No tasks assigned to you' : 'No tasks found',
                'message' => $mine ? 'Tasks assigned to you will show up here.' : 'Create a task when work is ready to assign.',
                'actionUrl' => (!$mine && can('tasks.create')) ? 'tasks/create' : null,
                'actionLabel' => (!$mine && can('tasks.create')) ? 'New task' : null,
            ]); ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table align-middle">
                    <thead>
                    <tr>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assignee</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due date</th>
                        <th>Progress</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasks as $task): ?>
                        <?php $canMove = \App\Services\TaskAccess::canChangeStatus($task); ?>
                        <tr>
                            <td>
                                <div class="wf-task-main <?= (int) $task->status_closed === 1 ? 'is-done' : '' ?>">
                                    <?php if ($canMove && $completeSlug && $reopenSlug): ?>
                                        <button class="wf-check <?= (int) $task->status_closed === 1 ? 'is-done' : '' ?>" type="button" data-url="<?= e(base_url('tasks/' . $task->id . '/status')) ?>" data-complete="<?= e($completeSlug) ?>" data-reopen="<?= e($reopenSlug) ?>" aria-pressed="<?= (int) $task->status_closed === 1 ? 'true' : 'false' ?>" aria-label="<?= (int) $task->status_closed === 1 ? 'Reopen' : 'Complete' ?> <?= e($task->title) ?>"><?= icon('check-lg') ?></button>
                                    <?php endif; ?>
                                    <a href="<?= e(base_url('tasks/' . $task->id)) ?>"><?= e($task->title) ?></a>
                                </div>
                            </td>
                            <td><?= e($task->project_name ?: '') ?></td>
                            <td><?= icon('person') ?> <?= e($task->assignee_name ?: 'Unassigned') ?></td>
                            <td><?php \App\Core\View::component('status-badge', ['status' => $task->priority]); ?></td>
                            <td><span data-status-label><?php \App\Core\View::component('status-badge', [
                                'status' => $task->status,
                                'label' => $task->status_name ?: null,
                                'color' => $task->status_color ?: null,
                            ]); ?></span></td>
                            <td><?= icon('calendar') ?> <?= e(format_date($task->due_date)) ?></td>
                            <td><?php \App\Core\View::component('progress-meter', ['percent' => (int) $task->status_closed === 1 ? 100 : (int) $task->progress]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
