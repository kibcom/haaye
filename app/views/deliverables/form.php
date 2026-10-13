<?php $editing = $deliverable !== null; ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Deliverables</p>
        <h2><?= $editing ? 'Edit deliverable' : 'New deliverable' ?></h2>
        <p class="text-muted mb-0">The client comes from the project. A status move follows the workflow.</p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($editing ? 'deliverables/' . $deliverable->id : 'deliverables')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $deliverable->name ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="project_id">Project</label>
                <select class="form-select" id="project_id" name="project_id" required>
                    <option value="">Select a project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project->id ?>" <?= selected(old('project_id', $deliverable->project_id ?? $prefillProject), $project->id) ?>><?= e($project->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach ($statuses as $item): ?>
                        <option value="<?= e($item->slug) ?>" <?= selected(old('status', $deliverable->status ?? 'draft'), $item->slug) ?>><?= e($item->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="due_date">Due date</label>
                <input class="form-control" id="due_date" type="date" name="due_date" value="<?= e((string) old('due_date', $deliverable->due_date ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="assignee_user_id">Assignee</label>
                <select class="form-select" id="assignee_user_id" name="assignee_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= selected(old('assignee_user_id', $deliverable->assignee_user_id ?? ''), $person->id) ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="task_id">Task</label>
                <select class="form-select" id="task_id" name="task_id">
                    <option value="">No task</option>
                    <?php foreach ($tasks as $task): ?>
                        <option value="<?= (int) $task->id ?>" <?= selected(old('task_id', $deliverable->task_id ?? ''), $task->id) ?>><?= e($task->title) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= e((string) old('description', $deliverable->description ?? '')) ?></textarea>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'plus-lg') ?> <?= $editing ? 'Save deliverable' : 'Create deliverable' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url($editing ? 'deliverables/' . $deliverable->id : 'deliverables')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
