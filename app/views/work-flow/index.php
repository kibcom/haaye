<?php
/** @var list<\App\Models\WorkFlowRole> $roles */
/** @var list<\App\Models\WorkFlowTask> $tasks */
/** @var list<\App\Models\ContentGrid> $contentGrids */
/** @var list<\App\Models\Project> $projects */
/** @var list<\App\Models\User> $people */
/** @var array{roles:int,active_roles:int,tasks:int,task_status:array<string,int>,grids:int} $stats */
$labels = $statusLabels ?? [];
$canManage = !empty($canManage);
$canAssign = !empty($canAssign);
$canViewGrids = !empty($canViewGrids);
$canManageGrids = !empty($canManageGrids);
$contentGrids = $contentGrids ?? [];
$stats = $stats ?? ['roles' => 0, 'active_roles' => 0, 'tasks' => 0, 'task_status' => [], 'grids' => 0];
$todo = (int) ($stats['task_status']['todo'] ?? 0);
$inProgress = (int) ($stats['task_status']['in_progress'] ?? 0);
$review = (int) ($stats['task_status']['review'] ?? 0);
$done = (int) ($stats['task_status']['done'] ?? 0);
?>
<div class="wf-hub">
    <section class="wf-hub-hero wf-hub-hero-compact">
        <div>
            <p class="wf-kicker mb-0">CRM · Production</p>
            <h2 class="wf-hub-title">Workflow</h2>
            <p class="wf-hub-lead mb-0">Roles, shared tasks (same as Operations), and monthly content grids.</p>
        </div>
        <div class="wf-hub-hero-actions">
            <?php if ($canManage): ?>
                <button class="btn btn-sm btn-outline" type="button" data-bs-toggle="modal" data-bs-target="#wf-role-create-modal"><?= icon('plus-lg') ?> Role</button>
            <?php endif; ?>
            <?php if ($canAssign): ?>
                <button class="btn btn-sm btn-outline" type="button" data-bs-toggle="modal" data-bs-target="#wf-task-create-modal"><?= icon('plus-lg') ?> Task</button>
            <?php endif; ?>
            <?php if ($canManageGrids): ?>
                <a class="btn btn-sm btn-aqua" href="<?= e(base_url('work-flow/content-grids')) ?>"><?= icon('grid-3x3-gap') ?> New grid</a>
            <?php elseif ($canViewGrids): ?>
                <a class="btn btn-sm btn-aqua" href="<?= e(base_url('work-flow/content-grids')) ?>"><?= icon('grid-3x3-gap') ?> Content grids</a>
            <?php endif; ?>
        </div>
    </section>

    <div class="wf-hub-tiles wf-hub-tiles-compact">
        <a class="wf-hub-tile wf-hub-tile-roles" href="#wf-panel-roles">
            <span class="wf-hub-tile-icon"><?= icon('diagram-3') ?></span>
            <span class="wf-hub-tile-body">
                <span class="wf-hub-tile-label">Roles</span>
                <strong><?= (int) $stats['roles'] ?></strong>
                <small><?= (int) $stats['active_roles'] ?> active</small>
            </span>
        </a>
        <a class="wf-hub-tile wf-hub-tile-tasks" href="#wf-panel-tasks">
            <span class="wf-hub-tile-icon"><?= icon('check2-square') ?></span>
            <span class="wf-hub-tile-body">
                <span class="wf-hub-tile-label">Tasks</span>
                <strong><?= (int) $stats['tasks'] ?></strong>
                <small><?= $todo ?> todo · <?= $done ?> done</small>
            </span>
        </a>
        <?php if ($canViewGrids): ?>
        <a class="wf-hub-tile wf-hub-tile-grids" href="#wf-panel-grids">
            <span class="wf-hub-tile-icon"><?= icon('calendar3') ?></span>
            <span class="wf-hub-tile-body">
                <span class="wf-hub-tile-label">Content grids</span>
                <strong><?= (int) $stats['grids'] ?></strong>
                <small>Per company</small>
            </span>
        </a>
        <?php endif; ?>
    </div>

    <?php if ($canViewGrids): ?>
    <section class="wf-card wf-hub-panel" id="wf-panel-grids">
        <div class="wf-card-head d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div>
                <h3 class="mb-0"><?= icon('calendar3') ?> Monthly content grids</h3>
                <p class="text-muted small mb-0">One content calendar per company — weeks, platforms, edited &amp; posted</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline" href="<?= e(base_url('work-flow/content-grids')) ?>">All grids</a>
                <?php if ($canManageGrids): ?>
                    <a class="btn btn-sm btn-aqua" href="<?= e(base_url('work-flow/content-grids')) ?>"><?= icon('plus-lg') ?> New grid</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($contentGrids)): ?>
            <?php \App\Core\View::component('empty-state', [
                'title' => 'No content grids yet',
                'message' => 'Create a monthly content grid for a company to track posts week by week.',
                'actionUrl' => $canManageGrids ? 'work-flow/content-grids' : null,
                'actionLabel' => $canManageGrids ? 'Open content grids' : null,
            ]); ?>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($contentGrids as $grid): ?>
                    <?php
                    $dayCount = max(1, (int) $grid->day_count);
                    $postedPct = (int) round(((int) $grid->posted_count / $dayCount) * 100);
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <a class="wf-hub-grid-card" href="<?= e(base_url('work-flow/content-grids/' . $grid->id)) ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <strong><?= e($grid->client_name ?: 'Company') ?></strong>
                                <span class="badge text-bg-light"><?= $postedPct ?>% posted</span>
                            </div>
                            <div class="text-muted small mt-1">
                                <?= e(format_date($grid->start_date)) ?> → <?= e(format_date($grid->end_date)) ?>
                            </div>
                            <div class="wf-hub-grid-meter mt-2">
                                <span style="width: <?= $postedPct ?>%"></span>
                            </div>
                            <div class="text-muted small mt-2">
                                Edited <?= (int) $grid->edited_count ?>/<?= (int) $grid->day_count ?>
                                · Posted <?= (int) $grid->posted_count ?>/<?= (int) $grid->day_count ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="wf-hub-status-strip">
        <div class="wf-hub-chip"><span>To do</span><strong><?= $todo ?></strong></div>
        <div class="wf-hub-chip"><span>In progress</span><strong><?= $inProgress ?></strong></div>
        <div class="wf-hub-chip"><span>Review</span><strong><?= $review ?></strong></div>
        <div class="wf-hub-chip"><span>Done</span><strong><?= $done ?></strong></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4" id="wf-panel-roles">
            <section class="wf-card wf-hub-panel h-100">
                <div class="wf-card-head d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="mb-0"><?= icon('diagram-3') ?> Workflow roles</h3>
                        <p class="text-muted small mb-0">Pipeline stages for production work</p>
                    </div>
                    <?php if ($canManage): ?>
                        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#wf-role-create-modal">
                            <?= icon('plus-lg') ?> Add
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($roles)): ?>
                    <p class="text-muted mb-0">No roles yet. Add Sales, Editor, Social Media, and the rest of your pipeline.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush wf-workflow-list">
                        <?php foreach ($roles as $role): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <div class="fw-semibold">
                                        <?= e($role->name) ?>
                                        <?php if (!(int) $role->is_active): ?>
                                            <span class="badge text-bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small text-truncate">
                                        <?= e($role->description ?: 'No description') ?> · <?= (int) $role->task_count ?> tasks
                                    </div>
                                </div>
                                <?php if ($canManage): ?>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <button
                                            class="btn btn-sm btn-outline"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#wf-role-edit-modal"
                                            data-id="<?= (int) $role->id ?>"
                                            data-name="<?= e($role->name) ?>"
                                            data-description="<?= e((string) $role->description) ?>"
                                            data-sort="<?= (int) $role->sort_order ?>"
                                            data-active="<?= (int) $role->is_active ?>"
                                        ><?= icon('pencil') ?> Edit</button>
                                        <form method="post" action="<?= e(base_url('work-flow/roles/' . $role->id . '/delete')) ?>" data-confirm="Remove this workflow role?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-lg-8" id="wf-panel-tasks">
            <section class="wf-card wf-hub-panel mb-3">
                <div class="wf-card-head d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="mb-0"><?= icon('check2-square') ?> Workflow tasks</h3>
                        <p class="text-muted small mb-0">Assign work by project and role</p>
                    </div>
                    <?php if ($canAssign): ?>
                        <button class="btn btn-sm btn-aqua" type="button" data-bs-toggle="modal" data-bs-target="#wf-task-create-modal">
                            <?= icon('plus-lg') ?> Add task
                        </button>
                    <?php endif; ?>
                </div>

                <form class="wf-filters mb-3" method="get">
                    <select class="form-select" name="project">
                        <option value="">All projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project->id ?>" <?= selected($projectId ?? 0, $project->id) ?>><?= e($project->code . ' · ' . $project->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($labels as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= selected($status ?? '', $value) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline btn-sm" type="submit">Filter</button>
                </form>

                <?php if (empty($tasks)): ?>
                    <?php \App\Core\View::component('empty-state', [
                        'title' => 'No workflow tasks',
                        'message' => 'Add a task for a project, pick a workflow role, and assign a teammate.',
                    ]); ?>
                <?php else: ?>
                    <ul class="list-group list-group-flush wf-workflow-list">
                        <?php foreach ($tasks as $task): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <div class="fw-semibold"><?= e($task->title) ?></div>
                                        <div class="text-muted small">
                                            <?= e(($task->project_code ? $task->project_code . ' · ' : '') . ($task->project_name ?: 'Project')) ?>
                                            · <?= e($task->role_name ?: 'Any role') ?>
                                            · <?= e($task->assignee_name ?: 'Unassigned') ?>
                                            · <span class="wf-hub-status wf-hub-status-<?= e($task->status) ?>"><?= e($labels[$task->status] ?? $task->status) ?></span>
                                            <?php if ($task->due_date): ?>
                                                · Due <?= e(format_date($task->due_date)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($task->description): ?>
                                            <div class="small mt-1"><?= e($task->description) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <?php if ($canAssign || (int) $task->assignee_user_id === (int) (auth_user()?->id ?? 0)): ?>
                                            <button
                                                class="btn btn-sm btn-outline"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#wf-task-edit-modal"
                                                data-id="<?= (int) $task->id ?>"
                                                data-title="<?= e($task->title) ?>"
                                                data-description="<?= e((string) $task->description) ?>"
                                                data-project="<?= (int) $task->project_id ?>"
                                                data-role="<?= (int) ($task->work_flow_role_id ?? 0) ?>"
                                                data-assignee="<?= (int) ($task->assignee_user_id ?? 0) ?>"
                                                data-status="<?= e($task->status) ?>"
                                                data-due="<?= e((string) $task->due_date) ?>"
                                                data-can-assign="<?= $canAssign ? '1' : '0' ?>"
                                            ><?= icon('pencil') ?> Edit</button>
                                        <?php endif; ?>
                                        <?php if ($canManage): ?>
                                            <form method="post" action="<?= e(base_url('work-flow/tasks/' . $task->id . '/delete')) ?>" data-confirm="Remove this workflow task?">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="wf-role-create-modal" tabindex="-1" aria-labelledby="wf-role-create-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= e(base_url('work-flow/roles')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="wf-role-create-title">Add workflow role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="create_role_name">Role name</label>
                    <input class="form-control" id="create_role_name" name="name" maxlength="120" required placeholder="e.g. Colorist">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="create_role_description">Description</label>
                    <input class="form-control" id="create_role_description" name="description" maxlength="255" placeholder="Optional">
                </div>
                <div>
                    <label class="form-label" for="create_role_sort">Order</label>
                    <input class="form-control" id="create_role_sort" type="number" name="sort_order" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= icon('plus-lg') ?> Add role</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="wf-role-edit-modal" tabindex="-1" aria-labelledby="wf-role-edit-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" id="wf-role-edit-form" action="">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="wf-role-edit-title">Edit workflow role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="edit_role_name">Role name</label>
                    <input class="form-control" id="edit_role_name" name="name" maxlength="120" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_role_description">Description</label>
                    <input class="form-control" id="edit_role_description" name="description" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_role_sort">Order</label>
                    <input class="form-control" id="edit_role_sort" type="number" name="sort_order" min="0">
                </div>
                <input type="hidden" name="is_active" value="0">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" id="edit_role_active" name="is_active" value="1">
                    Active
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= icon('check-lg') ?> Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canAssign): ?>
<div class="modal fade" id="wf-task-create-modal" tabindex="-1" aria-labelledby="wf-task-create-title" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="post" action="<?= e(base_url('work-flow/tasks')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="wf-task-create-title">Add workflow task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="create_task_title">Title</label>
                        <input class="form-control" id="create_task_title" name="title" maxlength="190" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="create_task_project">Project</label>
                        <select class="form-select" id="create_task_project" name="project_id" required>
                            <option value="">Select project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= (int) $project->id ?>"><?= e($project->code . ' · ' . $project->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="create_task_role">Workflow role</label>
                        <select class="form-select" id="create_task_role" name="work_flow_role_id">
                            <option value="">Any role</option>
                            <?php foreach ($roles as $role): ?>
                                <?php if ((int) $role->is_active !== 1) {
                                    continue;
                                } ?>
                                <option value="<?= (int) $role->id ?>"><?= e($role->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="create_task_assignee">Assign to</label>
                        <select class="form-select" id="create_task_assignee" name="assignee_user_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($people as $person): ?>
                                <option value="<?= (int) $person->id ?>"><?= e($person->name) ?><?= $person->role_name ? ' · ' . e($person->role_name) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="create_task_due">Due date</label>
                        <input class="form-control" id="create_task_due" type="date" name="due_date">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="create_task_description">Details</label>
                        <textarea class="form-control" id="create_task_description" name="description" rows="3" maxlength="4000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-aqua"><?= icon('plus-lg') ?> Add task</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="wf-task-edit-modal" tabindex="-1" aria-labelledby="wf-task-edit-title" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="post" id="wf-task-edit-form" action="">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="wf-task-edit-title">Edit workflow task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="wf-task-edit-full">
                    <div class="col-md-6">
                        <label class="form-label" for="edit_task_title">Title</label>
                        <input class="form-control" id="edit_task_title" name="title" maxlength="190" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="edit_task_project">Project</label>
                        <select class="form-select" id="edit_task_project" name="project_id" required>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= (int) $project->id ?>"><?= e($project->code . ' · ' . $project->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="edit_task_role">Workflow role</label>
                        <select class="form-select" id="edit_task_role" name="work_flow_role_id">
                            <option value="">Any role</option>
                            <?php foreach ($roles as $role): ?>
                                <?php if ((int) $role->is_active !== 1) {
                                    continue;
                                } ?>
                                <option value="<?= (int) $role->id ?>"><?= e($role->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="edit_task_assignee">Assign to</label>
                        <select class="form-select" id="edit_task_assignee" name="assignee_user_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($people as $person): ?>
                                <option value="<?= (int) $person->id ?>"><?= e($person->name) ?><?= $person->role_name ? ' · ' . e($person->role_name) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="edit_task_due">Due date</label>
                        <input class="form-control" id="edit_task_due" type="date" name="due_date">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="edit_task_status">Status</label>
                        <select class="form-select" id="edit_task_status" name="status">
                            <?php foreach ($labels as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="edit_task_description">Details</label>
                        <textarea class="form-control" id="edit_task_description" name="description" rows="3" maxlength="4000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= icon('check-lg') ?> Save</button>
            </div>
        </form>
    </div>
</div>

<style>
.wf-hub { display: flex; flex-direction: column; gap: 10px; }
.wf-hub-hero {
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}
.wf-hub-hero-compact .wf-kicker { font-size: 10px; margin-bottom: 0; }
.wf-hub-title { margin: 0; font-size: 18px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; }
.wf-hub-lead { color: var(--text-muted); font-size: 12px; margin-top: 2px; }
.wf-hub-hero-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.wf-hub-tiles {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
.wf-hub-tile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--surface);
    text-decoration: none;
    color: inherit;
    transition: border-color .15s ease;
}
.wf-hub-tile:hover {
    color: inherit;
    border-color: var(--primary);
}
.wf-hub-tile-icon {
    width: 30px; height: 30px; border-radius: 8px;
    display: grid; place-items: center;
    font-size: 14px;
    flex-shrink: 0;
}
.wf-hub-tile-roles .wf-hub-tile-icon { background: rgba(14, 165, 233, .14); color: #0284c7; }
.wf-hub-tile-tasks .wf-hub-tile-icon { background: rgba(34, 211, 238, .16); color: #0891b2; }
.wf-hub-tile-grids .wf-hub-tile-icon { background: rgba(234, 88, 12, .14); color: #ea580c; }
.wf-hub-tile-label { display: block; font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.wf-hub-tile-body strong { display: inline; font-size: 15px; letter-spacing: -.02em; margin-right: 4px; }
.wf-hub-tile-body small { color: var(--text-muted); font-size: 11px; }
.wf-hub-status-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
}
.wf-hub-chip {
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    padding: 6px 10px;
}
.wf-hub-chip span { display: block; color: var(--text-muted); font-size: 10px; font-weight: 600; }
.wf-hub-chip strong { font-size: 14px; letter-spacing: -.02em; }
.wf-hub-panel .wf-card-head h3 .bi { color: var(--primary); margin-right: 6px; }
.wf-hub-status {
    display: inline-block;
    font-weight: 700;
}
.wf-hub-status-todo { color: #64748b; }
.wf-hub-status-in_progress { color: #0284c7; }
.wf-hub-status-review { color: #c2410c; }
.wf-hub-status-done { color: #15803d; }
.wf-hub-grid-card {
    display: block;
    height: 100%;
    padding: 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: color-mix(in srgb, var(--surface) 92%, #fff7ed);
    text-decoration: none;
    color: inherit;
    transition: border-color .15s ease, transform .15s ease;
}
.wf-hub-grid-card:hover {
    color: inherit;
    border-color: #ea580c;
    transform: translateY(-1px);
}
.wf-hub-grid-meter {
    height: 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--border) 70%, transparent);
    overflow: hidden;
}
.wf-hub-grid-meter > span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #f97316, #ea580c);
    border-radius: inherit;
}
.wf-workflow-list .list-group-item { border-left: 0; border-right: 0; background: transparent; }
.wf-workflow-list .list-group-item:first-child { border-top: 0; }
.wf-workflow-list .min-w-0 { min-width: 0; }
#wf-panel-roles, #wf-panel-tasks, #wf-panel-grids { scroll-margin-top: 88px; }
@media (max-width: 992px) {
    .wf-hub-tiles { grid-template-columns: 1fr; }
    .wf-hub-status-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<script>
(function () {
    var roleEdit = document.getElementById('wf-role-edit-modal');
    if (roleEdit) {
        roleEdit.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            var id = button.getAttribute('data-id');
            var form = document.getElementById('wf-role-edit-form');
            form.action = <?= json_encode(base_url('work-flow/roles/')) ?> + id;
            document.getElementById('edit_role_name').value = button.getAttribute('data-name') || '';
            document.getElementById('edit_role_description').value = button.getAttribute('data-description') || '';
            document.getElementById('edit_role_sort').value = button.getAttribute('data-sort') || '0';
            document.getElementById('edit_role_active').checked = button.getAttribute('data-active') === '1';
        });
    }

    var taskEdit = document.getElementById('wf-task-edit-modal');
    if (taskEdit) {
        taskEdit.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;
            var id = button.getAttribute('data-id');
            var form = document.getElementById('wf-task-edit-form');
            form.action = <?= json_encode(base_url('work-flow/tasks/')) ?> + id;
            document.getElementById('edit_task_title').value = button.getAttribute('data-title') || '';
            document.getElementById('edit_task_description').value = button.getAttribute('data-description') || '';
            document.getElementById('edit_task_project').value = button.getAttribute('data-project') || '';
            document.getElementById('edit_task_role').value = button.getAttribute('data-role') || '';
            document.getElementById('edit_task_assignee').value = button.getAttribute('data-assignee') || '';
            document.getElementById('edit_task_status').value = button.getAttribute('data-status') || 'todo';
            document.getElementById('edit_task_due').value = button.getAttribute('data-due') || '';

            var canAssign = button.getAttribute('data-can-assign') === '1';
            var full = document.getElementById('wf-task-edit-full');
            ['edit_task_title', 'edit_task_description', 'edit_task_project', 'edit_task_role', 'edit_task_assignee', 'edit_task_due'].forEach(function (idName) {
                var el = document.getElementById(idName);
                if (!el) return;
                el.disabled = !canAssign;
                el.required = canAssign && (idName === 'edit_task_title' || idName === 'edit_task_project');
                var wrap = el.closest('.col-md-6, .col-md-4, .col-12');
                if (wrap) {
                    wrap.hidden = !canAssign;
                }
            });
            var statusWrap = document.getElementById('edit_task_status');
            if (statusWrap && statusWrap.closest('.col-md-4')) {
                statusWrap.closest('.col-md-4').hidden = false;
            }
            if (full && !canAssign) {
                document.getElementById('wf-task-edit-title').textContent = 'Update task status';
            } else if (full) {
                document.getElementById('wf-task-edit-title').textContent = 'Edit workflow task';
            }
        });
    }
})();
</script>
