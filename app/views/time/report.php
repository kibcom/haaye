<?php
$renderRows = static function (array $rows, string $empty): void {
    if ($rows === []) {
        echo '<p class="text-muted mb-0">' . e($empty) . '</p>';
        return;
    }
    echo '<div class="table-responsive"><table class="table wf-table align-middle mb-0"><thead><tr><th>Name</th><th>Hours</th><th>Billable</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . e((string) $row['bucket_name']) . '</td><td>' . e(format_minutes((int) $row['minutes'])) . '</td><td>' . e(format_minutes((int) $row['billable_minutes'])) . '</td></tr>';
    }
    echo '</tbody></table></div>';
};
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Time</p>
        <h2>Productivity</h2>
        <p class="text-muted mb-0"><?= e(format_minutes((int) $minutes)) ?> logged from <?= e(format_date($from)) ?> to <?= e(format_date($to)) ?>. These hours are not invoices.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(base_url('time')) ?>"><?= icon('calendar-week') ?> Timesheet</a>
</div>

<section class="wf-card mb-3">
    <form method="get" action="<?= e(base_url('time/report')) ?>" class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="from">From</label>
            <input class="form-control" id="from" type="date" name="from" value="<?= e($from) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="to">To</label>
            <input class="form-control" id="to" type="date" name="to" value="<?= e($to) ?>">
        </div>
        <?php if ($people): ?>
            <div class="col-md-3">
                <label class="form-label" for="report-user">Employee</label>
                <select class="form-select" id="report-user" name="user">
                    <option value="0">Everyone</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= (int) $person->id === (int) $userId ? 'selected' : '' ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label" for="report-project">Project</label>
            <select class="form-select" id="report-project" name="project">
                <option value="0">All visible projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project->id ?>" <?= (int) $project->id === (int) $projectId ? 'selected' : '' ?>><?= e($project->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?= icon('arrow-repeat') ?> Update report</button>
        </div>
    </form>
</section>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="wf-card">
            <div class="wf-card-head"><h3>By employee</h3></div>
            <?php $renderRows($byUser, 'No time in this range.'); ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="wf-card">
            <div class="wf-card-head"><h3>By project</h3></div>
            <?php $renderRows($byProject, 'No time in this range.'); ?>
        </section>
    </div>
    <div class="col-12">
        <section class="wf-card">
            <div class="wf-card-head"><h3>By task</h3></div>
            <?php $renderRows($byTask, 'No time in this range.'); ?>
        </section>
    </div>
</div>
