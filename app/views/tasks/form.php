<?php $editing = $task !== null; ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Tasks</p>
        <h2><?= $editing ? 'Edit task' : 'New task' ?></h2>
        <p class="text-muted mb-0">A task belongs to one project. The client comes from that project.</p>
    </div>
</div>

<section class="wf-card">
    <?php if (empty($projects)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No projects available',
            'message' => 'You need a project you can access before creating a task.',
        ]); ?>
    <?php else: ?>
        <form method="post" action="<?= e(base_url($editing ? 'tasks/' . $task->id : 'tasks')) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">Title</label>
                    <input class="form-control" id="title" name="title" value="<?= e((string) old('title', $task->title ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="project_id">Project</label>
                    <select class="form-select" id="project_id" name="project_id" required>
                        <option value="">Select a project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project->id ?>" <?= selected(old('project_id', $task->project_id ?? $prefillProject), $project->id) ?>><?= e($project->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach ($statuses as $item): ?>
                            <option value="<?= e($item->slug) ?>" <?= selected(old('status', $task->status ?? 'todo'), $item->slug) ?>><?= e($item->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="priority">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= selected(old('priority', $task->priority ?? 'medium'), $value) ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="start_date">Start date</label>
                    <input class="form-control" id="start_date" type="date" name="start_date" value="<?= e((string) old('start_date', $task->start_date ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="due_date">Due date</label>
                    <input class="form-control" id="due_date" type="date" name="due_date" value="<?= e((string) old('due_date', $task->due_date ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="assignee_user_id">Assignee</label>
                    <?php if ($canAssign): ?>
                        <select class="form-select" id="assignee_user_id" name="assignee_user_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($people as $person): ?>
                                <option value="<?= (int) $person->id ?>" <?= selected(old('assignee_user_id', $task->assignee_user_id ?? ''), $person->id) ?>><?= e($person->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input class="form-control" id="assignee_user_id" value="<?= e($task->assignee_name ?? 'Unassigned') ?>" disabled>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?= e((string) old('description', $task->description ?? '')) ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'plus-lg') ?> <?= $editing ? 'Save task' : 'Create task' ?></button>
                <a class="btn btn-secondary" href="<?= e(base_url($editing ? 'tasks/' . $task->id : 'tasks')) ?>"><?= icon('x-lg') ?> Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>
