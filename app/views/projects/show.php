<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Project dashboard</p>
        <h2><?= e($project->name) ?></h2>
        <p class="text-muted mb-0">
            <?= e($project->client_name ?: 'Client') ?>
            <?php if ($project->code): ?> · <?= e($project->code) ?><?php endif; ?>
        </p>
        <?php if (!empty($autoTracking) && !empty($runningTimer) && (int) $runningTimer->project_id === (int) $project->id): ?>
            <p class="wf-task-auto-time mb-0 mt-2"><?= icon('stopwatch') ?> Auto-tracking time on this project<?= $runningTimer->task_id ? ' (via task)' : '' ?></p>
        <?php endif; ?>
    </div>
    <?php if ($canEdit): ?>
        <a class="btn btn-soft-primary" href="<?= e(base_url('projects/' . $project->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="wf-stat">
            <span>Status</span>
            <strong><?= e($project->status_name ?: ucwords(str_replace('_', ' ', $project->status))) ?></strong>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat">
            <span>Priority</span>
            <strong><?= e(ucfirst($project->priority)) ?></strong>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat">
            <span>Progress</span>
            <?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat">
            <span>Due date</span>
            <strong><?= e(format_date($project->due_date)) ?></strong>
        </div>
    </div>
</div>

<ul class="nav nav-tabs wf-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">Overview</button></li>
    <?php if (can('tasks.view')): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tasks" type="button">Tasks</button></li>
    <?php endif; ?>
    <?php if (can('pipeline.view') && feature_enabled('pipeline')): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#workflow" type="button">Workflow</button></li>
    <?php endif; ?>
    <?php if (can('deliverables.view')): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#deliverables" type="button">Deliverables</button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#members" type="button">Team</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#milestones" type="button">Milestones</button></li>
    <?php if (can('bookings.view') && feature_enabled('bookings')): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bookings" type="button">Bookings</button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity" type="button">Activity</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="overview">
        <div class="row g-3 mt-1">
            <div class="col-lg-7">
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Details</h3></div>
                    <dl class="wf-dl">
                        <div><dt>Client</dt><dd><?php if (can('clients.view')): ?><a href="<?= e(base_url('clients/' . $project->client_id)) ?>"><?= e($project->client_name ?: 'Client') ?></a><?php else: ?><?= e($project->client_name ?: '') ?><?php endif; ?></dd></div>
                        <div><dt>Project manager</dt><dd><?= e($project->manager_name ?: 'Unassigned') ?></dd></div>
                        <div><dt>Start date</dt><dd><?= e(format_date($project->start_date)) ?></dd></div>
                        <div><dt>Billing</dt><dd><?= e(ucwords(str_replace('_', ' ', $project->billing_type))) ?></dd></div>
                        <div><dt>Budget</dt><dd><?= $project->budget !== null && $project->budget !== '' ? e($currency . ' ' . number_format((float) $project->budget, 2)) : '' ?></dd></div>
                        <div><dt>Payment status</dt><dd><?php \App\Core\View::component('status-badge', ['status' => $project->payment_status]); ?></dd></div>
                        <div><dt>Created</dt><dd><?= e(format_datetime($project->created_at)) ?></dd></div>
                    </dl>
                    <?php if ($project->description): ?>
                        <p class="mt-3 mb-0"><?= nl2br(e($project->description)) ?></p>
                    <?php endif; ?>
                    <p class="text-muted small mt-3 mb-0">Budget and payment status describe the engagement. They are not an invoice balance.</p>
                </section>
                <?php if (!empty($finance)): ?>
                    <?php $profit = (float) $finance['revenue'] - (float) $finance['expenses']; ?>
                    <section class="wf-card mt-3">
                        <div class="wf-card-head"><h3>Profitability</h3></div>
                        <dl class="wf-dl">
                            <div><dt>Budget</dt><dd><?= $project->budget !== null && $project->budget !== '' ? e(money($currency, $project->budget)) : '' ?></dd></div>
                            <div><dt>Revenue</dt><dd><?= e(money($currency, $finance['revenue'])) ?></dd></div>
                            <div><dt>Expenses</dt><dd><?= e(money($currency, $finance['expenses'])) ?></dd></div>
                            <div><dt>Profit</dt><dd><?= e(money($currency, $profit)) ?></dd></div>
                            <div><dt>Outstanding</dt><dd><?= e(money($currency, $finance['outstanding'])) ?></dd></div>
                        </dl>
                        <p class="text-muted small mb-0">Revenue is invoiced totals. Expenses are approved costs. This is not a full set of accounts.</p>
                    </section>
                <?php endif; ?>
            </div>
            <div class="col-lg-5">
                <?php if (can('tasks.view')): ?>
                    <section class="wf-card mb-3">
                        <div class="wf-card-head">
                            <h3>Tasks</h3>
                            <div class="d-flex gap-2 align-items-center">
                                <a href="<?= e(base_url('tasks?project_id=' . $project->id)) ?>">All tasks</a>
                                <?php if (!empty($canCreateTask)): ?>
                                    <a class="btn btn-sm btn-aqua" href="<?= e(base_url('tasks/create?project=' . $project->id)) ?>"><?= icon('plus-lg') ?> New</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (empty($tasks)): ?>
                            <?php \App\Core\View::component('empty-state', [
                                'title' => 'No tasks yet',
                                'message' => 'Add the work that needs to be done on this project.',
                                'actionUrl' => !empty($canCreateTask) ? 'tasks/create?project=' . $project->id : null,
                                'actionLabel' => !empty($canCreateTask) ? 'New task' : null,
                            ]); ?>
                        <?php else: ?>
                            <ul class="wf-dash-list">
                                <?php foreach ($tasks as $task): ?>
                                    <li>
                                        <a href="<?= e(base_url('tasks/' . $task->id)) ?>">
                                            <span>
                                                <strong><?= e($task->title) ?></strong>
                                                <small><?= e($task->assignee_name ?: 'Unassigned') ?> · <?= e(format_date($task->due_date)) ?></small>
                                            </span>
                                            <?php \App\Core\View::component('status-badge', [
                                                'status' => $task->status,
                                                'label' => $task->status_name ?: null,
                                                'color' => $task->status_color ?: null,
                                            ]); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ((int) $taskCount > count($tasks)): ?>
                                <p class="mb-0 mt-2"><a href="<?= e(base_url('tasks?project_id=' . $project->id)) ?>">View all <?= (int) $taskCount ?> tasks</a></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Recent activity</h3></div>
                    <?php if (empty($activity)): ?>
                        <?php \App\Core\View::component('empty-state', [
                            'title' => 'No activity yet',
                            'message' => 'Updates to this project will appear here.',
                        ]); ?>
                    <?php else: ?>
                        <ul class="wf-timeline">
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
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="members">
        <div class="row g-3 mt-1">
            <div class="<?= $canManageMembers ? 'col-lg-7' : 'col-12' ?>">
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Team on this project</h3></div>
                    <?php if (empty($members)): ?>
                        <?php \App\Core\View::component('empty-state', [
                            'title' => 'No members yet',
                            'message' => 'Add the people responsible for this project.',
                        ]); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table wf-table align-middle">
                                <thead>
                                <tr><th>Person</th><th>Role</th><th></th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($member->user_name ?: 'User') ?></strong>
                                            <div class="text-muted small"><?= e($member->job_title ?: '') ?></div>
                                        </td>
                                        <td>
                                            <?php if ($canManageMembers): ?>
                                                <form method="post" action="<?= e(base_url('projects/' . $project->id . '/members/' . $member->id . '/role')) ?>" data-confirm="Are you sure you want to change this role?">
                                                    <?= csrf_field() ?>
                                                    <label class="visually-hidden" for="role-<?= (int) $member->id ?>">Role for <?= e($member->user_name ?: 'this person') ?></label>
                                                    <select class="form-select form-select-sm" id="role-<?= (int) $member->id ?>" name="member_role" onchange="this.form.requestSubmit()">
                                                        <option value="member" <?= selected($member->member_role, 'member') ?>>Member</option>
                                                        <option value="manager" <?= selected($member->member_role, 'manager') ?>>Manager</option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <?= e($member->member_role === 'manager' ? 'Manager' : 'Member') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($canManageMembers): ?>
                                                <form method="post" action="<?= e(base_url('projects/' . $project->id . '/members/' . $member->id . '/remove')) ?>" data-confirm="Are you sure you want to remove this member?">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <?php if ($canManageMembers): ?>
                <div class="col-lg-5">
                    <section class="wf-card">
                        <div class="wf-card-head"><h3>Add people</h3></div>
                        <?php
                        $onTeam = [];
                        foreach ($members as $member) {
                            $onTeam[] = (int) $member->user_id;
                        }
                        $available = array_values(array_filter($people, static function ($person) use ($onTeam): bool {
                            return !in_array((int) $person->id, $onTeam, true);
                        }));
                        ?>
                        <?php if ($available === []): ?>
                            <p class="text-muted mb-0">Everyone in the company is already on this team.</p>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('projects/' . $project->id . '/members')) ?>">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <span class="form-label">People</span>
                                    <div class="wf-member-pick">
                                        <label class="form-check is-all">
                                            <input class="form-check-input" type="checkbox" data-check-all="project-member">
                                            <span class="form-check-label">Select all</span>
                                        </label>
                                        <?php foreach ($available as $person): ?>
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?= (int) $person->id ?>" data-member-choice="project-member">
                                                <span class="form-check-label"><?= e($person->name) ?><?php if (!empty($person->job_title)): ?> <small class="text-muted"><?= e($person->job_title) ?></small><?php endif; ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="member_role">Role for the selected people</label>
                                    <select class="form-select" id="member_role" name="member_role">
                                        <option value="member">Member</option>
                                        <option value="manager">Manager</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary" type="submit"><?= icon('person-plus') ?> Add to team</button>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="milestones">
        <div class="row g-3 mt-1">
            <div class="<?= $canManageMilestones ? 'col-lg-7' : 'col-12' ?>">
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Milestones</h3></div>
                    <?php if (empty($milestones)): ?>
                        <?php \App\Core\View::component('empty-state', [
                            'title' => 'No milestones yet',
                            'message' => 'Break the project into dated checkpoints.',
                        ]); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table wf-table align-middle">
                                <thead>
                                <tr>
                                    <th>Milestone</th>
                                    <th>Status</th>
                                    <th>Due</th>
                                    <th>Owner</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($milestones as $milestone): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($milestone->name) ?></strong>
                                            <?php \App\Core\View::component('progress-meter', ['percent' => (int) $milestone->progress]); ?>
                                        </td>
                                        <td><?php \App\Core\View::component('status-badge', ['status' => $milestone->status]); ?></td>
                                        <td><?= e(format_date($milestone->due_date)) ?></td>
                                        <td><?= e($milestone->owner_name ?: '') ?></td>
                                        <td class="text-end">
                                            <?php if ($canManageMilestones): ?>
                                                <a href="<?= e(base_url('projects/' . $project->id . '/milestones/' . $milestone->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <?php if ($canManageMilestones): ?>
                <div class="col-lg-5">
                    <section class="wf-card">
                        <div class="wf-card-head"><h3>Add milestone</h3></div>
                        <form method="post" action="<?= e(base_url('projects/' . $project->id . '/milestones')) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="milestone_name">Name</label>
                                <input class="form-control" id="milestone_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="milestone_due">Due date</label>
                                <input class="form-control" id="milestone_due" type="date" name="due_date">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="milestone_status">Status</label>
                                <select class="form-select" id="milestone_status" name="status">
                                    <option value="open">Open</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="milestone_progress">Progress (%)</label>
                                <input class="form-control" id="milestone_progress" type="number" min="0" max="100" name="progress" value="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="milestone_owner">Owner</label>
                                <select class="form-select" id="milestone_owner" name="owner_user_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($people as $person): ?>
                                        <option value="<?= (int) $person->id ?>"><?= e($person->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="milestone_description">Description</label>
                                <textarea class="form-control" id="milestone_description" name="description" rows="2"></textarea>
                            </div>
                            <button class="btn btn-primary" type="submit"><?= icon('flag') ?> Add milestone</button>
                        </form>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (can('bookings.view') && feature_enabled('bookings')): ?>
        <div class="tab-pane fade" id="bookings">
            <section class="wf-card mt-3">
                <div class="wf-card-head">
                    <h3>Bookings</h3>
                    <?php if (can('bookings.create')): ?>
                        <a class="btn btn-sm btn-aqua" href="<?= e(base_url('bookings/create?project=' . $project->id)) ?>"><?= icon('plus-lg') ?> New booking</a>
                    <?php endif; ?>
                </div>
                <?php if (empty($bookings)): ?>
                    <?php \App\Core\View::component('empty-state', [
                        'title' => 'No bookings yet',
                        'message' => 'Schedule a meeting or reserved time for this project.',
                        'actionUrl' => can('bookings.create') ? 'bookings/create?project=' . $project->id : null,
                        'actionLabel' => can('bookings.create') ? 'New booking' : null,
                    ]); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table wf-table align-middle">
                            <thead>
                            <tr>
                                <th>Booking</th>
                                <th>When</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(base_url('bookings/' . $booking->id)) ?>"><strong><?= e($booking->title) ?></strong></a>
                                        <div class="text-muted small"><?= e($booking->contact_name ?: $booking->location ?: '') ?></div>
                                    </td>
                                    <td><?= e(format_datetime($booking->starts_at)) ?></td>
                                    <td><?php \App\Core\View::component('status-badge', ['status' => $booking->status]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

    <?php if (can('tasks.view')): ?>
        <div class="tab-pane fade" id="tasks">
            <section class="wf-card mt-3">
                <div class="wf-card-head">
                    <h3>Tasks</h3>
                    <div class="d-flex gap-2">
                        <a href="<?= e(base_url('tasks?project_id=' . $project->id)) ?>">All tasks</a>
                        <?php if (!empty($canCreateTask)): ?>
                            <a class="btn btn-sm btn-aqua" href="<?= e(base_url('tasks/create?project=' . $project->id)) ?>"><?= icon('plus-lg') ?> New task</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (empty($tasks)): ?>
                    <?php \App\Core\View::component('empty-state', [
                        'title' => 'No tasks yet',
                        'message' => 'Add the work that needs to be done on this project.',
                        'actionUrl' => !empty($canCreateTask) ? 'tasks/create?project=' . $project->id : null,
                        'actionLabel' => !empty($canCreateTask) ? 'New task' : null,
                    ]); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table wf-table align-middle">
                            <thead>
                            <tr>
                                <th>Task</th>
                                <th>Assignee</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th>Progress</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><a href="<?= e(base_url('tasks/' . $task->id)) ?>"><strong><?= e($task->title) ?></strong></a></td>
                                    <td><?= e($task->assignee_name ?: 'Unassigned') ?></td>
                                    <td><?php \App\Core\View::component('status-badge', [
                                        'status' => $task->status,
                                        'label' => $task->status_name ?: null,
                                        'color' => $task->status_color ?: null,
                                    ]); ?></td>
                                    <td><?= e(format_date($task->due_date)) ?></td>
                                    <td><?php \App\Core\View::component('progress-meter', ['percent' => (int) ($task->status_closed ?? 0) === 1 ? 100 : (int) $task->progress]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ((int) $taskCount > count($tasks)): ?>
                        <p class="mb-0"><a href="<?= e(base_url('tasks?project_id=' . $project->id)) ?>">View all <?= (int) $taskCount ?> tasks</a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

    <?php if (can('deliverables.view')): ?>
        <div class="tab-pane fade" id="deliverables">
            <section class="wf-card mt-3">
                <div class="wf-card-head">
                    <h3>Deliverables</h3>
                    <?php if (!empty($canCreateDeliverable)): ?>
                        <a class="btn btn-sm btn-aqua" href="<?= e(base_url('deliverables/create?project=' . $project->id)) ?>"><?= icon('plus-lg') ?> New deliverable</a>
                    <?php endif; ?>
                </div>
                <?php if (empty($deliverables)): ?>
                    <?php \App\Core\View::component('empty-state', [
                        'title' => 'No deliverables yet',
                        'message' => 'Add a file or version when it is ready for review.',
                    ]); ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table wf-table align-middle">
                            <thead><tr><th>Deliverable</th><th>Status</th><th>Client approval</th><th>Version</th></tr></thead>
                            <tbody>
                            <?php foreach ($deliverables as $item): ?>
                                <tr>
                                    <td><a href="<?= e(base_url('deliverables/' . $item->id)) ?>"><strong><?= e($item->name) ?></strong></a></td>
                                    <td><?= e($item->status_name ?: ucwords(str_replace('_', ' ', $item->status))) ?></td>
                                    <td><?= e($item->client_approval_status === 'none' ? 'Not requested' : ucwords(str_replace('_', ' ', $item->client_approval_status))) ?></td>
                                    <td>v<?= (int) $item->version ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

    <?php if (can('pipeline.view') && feature_enabled('pipeline')): ?>
        <div class="tab-pane fade" id="workflow">
            <div class="row g-3 mt-1">
                <div class="<?= !empty($canStartPipeline) ? 'col-lg-7' : 'col-12' ?>">
                    <section class="wf-card">
                        <div class="wf-card-head">
                            <h3>Pipeline runs</h3>
                            <?php if (can('pipeline.view')): ?>
                                <a href="<?= e(base_url('pipeline')) ?>">All pipelines</a>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($pipelineRuns)): ?>
                            <?php \App\Core\View::component('empty-state', [
                                'title' => 'No workflow started',
                                'message' => 'Start a production pipeline template on this project.',
                            ]); ?>
                        <?php else: ?>
                            <ul class="wf-dash-list">
                                <?php foreach ($pipelineRuns as $run): ?>
                                    <li>
                                        <a href="<?= e(base_url('pipeline/runs/' . $run->id)) ?>">
                                            <span>
                                                <strong><?= e($run->name) ?></strong>
                                                <small><?= e(ucfirst($run->progression_mode)) ?>
                                                    <?php if ($run->current_stage_name): ?> · <?= e($run->current_stage_name) ?><?php endif; ?>
                                                </small>
                                            </span>
                                            <?php \App\Core\View::component('status-badge', ['status' => $run->status]); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>
                <?php if (!empty($canStartPipeline)): ?>
                    <div class="col-lg-5">
                        <section class="wf-card">
                            <div class="wf-card-head"><h3>Launch pipeline (CEO)</h3></div>
                            <p class="text-muted small">Template is set up once. Pick who does each stage, click once — tasks create &amp; assign. You approve when they submit.</p>
                            <?php if (empty($pipelineTemplates)): ?>
                                <p class="text-muted mb-2">No active templates.</p>
                                <?php if (can('pipeline.templates')): ?>
                                    <a class="btn btn-sm btn-outline" href="<?= e(base_url('pipeline/templates/create')) ?>">Create template</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                $stagesByTpl = $pipelineTemplateStages ?? [];
                                $firstTplId = (int) ($pipelineTemplates[0]->id ?? 0);
                                ?>
                                <form method="post" action="<?= e(base_url('pipeline/runs')) ?>" id="ceo-pipeline-launch">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                                    <div class="mb-3">
                                        <label class="form-label" for="template_id">Pipeline template</label>
                                        <select class="form-select" id="template_id" name="template_id" required>
                                            <?php foreach ($pipelineTemplates as $tpl): ?>
                                                <option value="<?= (int) $tpl->id ?>"><?= e($tpl->name) ?> · <?= (int) ($tpl->stage_count ?? 0) ?> stages</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2"><strong class="small">Assign each stage</strong></div>
                                    <?php foreach ($stagesByTpl as $tplId => $stageList): ?>
                                        <div class="pipeline-stage-assignees" data-template="<?= (int) $tplId ?>" <?= (int) $tplId !== $firstTplId ? 'hidden' : '' ?>>
                                            <?php foreach ($stageList as $stage): ?>
                                                <div class="mb-2">
                                                    <label class="form-label mb-0" for="assignee-<?= (int) $tplId ?>-<?= (int) $stage->id ?>"><?= e($stage->name) ?></label>
                                                    <select class="form-select form-select-sm" id="assignee-<?= (int) $tplId ?>-<?= (int) $stage->id ?>" name="assignees[<?= (int) $stage->id ?>]" <?= (int) $tplId === $firstTplId ? 'required' : 'disabled' ?>>
                                                        <option value="">Select person…</option>
                                                        <?php foreach ($people as $person): ?>
                                                            <option value="<?= (int) $person->id ?>" <?= selected($stage->default_assignee_user_id, $person->id) ?>><?= e($person->name) ?><?= !empty($person->role_name) ? ' · ' . e($person->role_name) : '' ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <button class="btn btn-primary w-100 mt-2" type="submit"><?= icon('play-fill') ?> Create &amp; assign first task</button>
                                </form>
                                <script>
                                (function () {
                                    var sel = document.getElementById('template_id');
                                    if (!sel) return;
                                    sel.addEventListener('change', function () {
                                        var id = sel.value;
                                        document.querySelectorAll('.pipeline-stage-assignees').forEach(function (box) {
                                            var on = box.getAttribute('data-template') === id;
                                            box.hidden = !on;
                                            box.querySelectorAll('select').forEach(function (s) {
                                                s.disabled = !on;
                                                s.required = on;
                                            });
                                        });
                                    });
                                })();
                                </script>
                            <?php endif; ?>
                        </section>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="tab-pane fade" id="activity">
        <section class="wf-card mt-3">
            <div class="wf-card-head"><h3>Activity history</h3></div>
            <?php if (empty($activity)): ?>
                <?php \App\Core\View::component('empty-state', [
                    'title' => 'No activity yet',
                    'message' => 'Updates to this project will appear here.',
                ]); ?>
            <?php else: ?>
                <ul class="wf-timeline">
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
    </div>
</div>
