<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Milestones</p>
        <h2>Edit milestone</h2>
        <p class="text-muted mb-0"><?= e($project->name) ?></p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url('projects/' . $project->id . '/milestones/' . $milestone->id)) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $milestone->name)) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="owner_user_id">Owner</label>
                <select class="form-select" id="owner_user_id" name="owner_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= selected(old('owner_user_id', $milestone->owner_user_id), $person->id) ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="due_date">Due date</label>
                <input class="form-control" id="due_date" type="date" name="due_date" value="<?= e((string) old('due_date', $milestone->due_date)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (['open' => 'Open', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('status', $milestone->status), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="progress">Progress (%)</label>
                <input class="form-control" id="progress" type="number" min="0" max="100" name="progress" value="<?= e((string) old('progress', $milestone->progress)) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e((string) old('description', $milestone->description)) ?></textarea>
            </div>
        </div>
        <?php if ($milestone->completed_at): ?>
            <p class="text-muted small mt-3 mb-0">Completed <?= e(format_datetime($milestone->completed_at)) ?>.</p>
        <?php endif; ?>
        <div class="mt-4 d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save milestone</button>
            <a class="btn btn-secondary" href="<?= e(base_url('projects/' . $project->id)) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
    <form class="mt-3" method="post" action="<?= e(base_url('projects/' . $project->id . '/milestones/' . $milestone->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this milestone?">
        <?= csrf_field() ?>
        <button class="btn btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove milestone</button>
    </form>
</section>
