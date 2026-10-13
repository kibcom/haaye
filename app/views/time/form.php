<?php
$isEdit = $entry instanceof \App\Models\TimeEntry;
$selectedProject = (int) old('project_id', $prefillProject);
$selectedTask = (int) old('task_id', $prefillTask);
$selectedUser = (int) old('user_id', $isEdit ? $entry->user_id : \App\Core\Auth::id());
$workDate = (string) old('work_date', $isEdit ? substr($entry->started_at, 0, 10) : date('Y-m-d'));
$startTime = (string) old('start_time', $isEdit ? substr($entry->started_at, 11, 5) : '09:00');
$endTime = (string) old('end_time', $isEdit && $entry->ended_at ? substr($entry->ended_at, 11, 5) : '10:00');
$billable = (string) old('billable', $isEdit ? (string) $entry->billable : '1');
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Time</p>
        <h2><?= $isEdit ? 'Edit time' : 'Log time' ?></h2>
        <p class="text-muted mb-0">Enter the start and end on the same day. Hours are not an invoice.</p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($isEdit ? 'time/' . $entry->id : 'time')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="time">
        <div class="row g-3">
            <?php if (!$isEdit && $people): ?>
                <div class="col-md-6">
                    <label class="form-label" for="user_id">Employee</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person->id ?>" <?= (int) $person->id === $selectedUser ? 'selected' : '' ?>><?= e($person->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label" for="time-project">Project</label>
                <select class="form-select <?= has_error('project_id') ? 'is-invalid' : '' ?>" id="time-project" name="project_id" required>
                    <option value="">Select a project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project->id ?>" <?= (int) $project->id === $selectedProject ? 'selected' : '' ?>><?= e($project->code . ' · ' . $project->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($message = error('project_id')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="time-task">Task</label>
                <select class="form-select" id="time-task" name="task_id">
                    <option value="">No specific task</option>
                    <?php foreach ($tasks as $task): ?>
                        <option value="<?= (int) $task->id ?>" data-project="<?= (int) $task->project_id ?>" <?= (int) $task->id === $selectedTask ? 'selected' : '' ?>><?= e($task->title) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="work_date">Date</label>
                <input class="form-control <?= has_error('work_date') ? 'is-invalid' : '' ?>" id="work_date" type="date" name="work_date" value="<?= e($workDate) ?>" required>
                <?php if ($message = error('work_date')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="start_time">Start</label>
                <input class="form-control <?= has_error('start_time') ? 'is-invalid' : '' ?>" id="start_time" type="time" name="start_time" value="<?= e($startTime) ?>" required>
                <?php if ($message = error('start_time')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="end_time">End</label>
                <input class="form-control <?= has_error('end_time') ? 'is-invalid' : '' ?>" id="end_time" type="time" name="end_time" value="<?= e($endTime) ?>" required>
                <?php if ($message = error('end_time')): ?><div class="invalid-feedback"><?= e($message) ?></div><?php endif; ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="note">Note</label>
                <textarea class="form-control" id="note" name="note" rows="3" maxlength="2000"><?= e((string) old('note', $isEdit ? ($entry->note ?? '') : '')) ?></textarea>
            </div>
            <div class="col-12">
                <input type="hidden" name="billable" value="0">
                <div class="form-check">
                    <input class="form-check-input" id="billable" type="checkbox" name="billable" value="1" <?= $billable === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="billable">Billable</label>
                </div>
                <div class="form-text">Billable is a label for later reporting. It does not create an invoice.</div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check-lg' : 'clock') ?> <?= $isEdit ? 'Save time' : 'Log time' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url('time')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
