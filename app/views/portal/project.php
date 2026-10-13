<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Project</p>
        <h2><?= e($project->name) ?></h2>
        <p class="text-muted mb-0"><?= e($project->code) ?></p>
    </div>
    <?php \App\Core\View::component('status-badge', ['status' => $project->status, 'label' => $project->status_name, 'color' => $project->status_color]); ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="wf-stat">
            <span>Progress</span>
            <?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="wf-stat">
            <span>Start</span>
            <strong><?= e(format_date($project->start_date)) ?></strong>
        </div>
    </div>
    <div class="col-md-4">
        <div class="wf-stat">
            <span>Due</span>
            <strong><?= e(format_date($project->due_date)) ?></strong>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <?php if ($project->description): ?>
            <section class="wf-card mb-3">
                <p class="mb-0"><?= nl2br(e($project->description)) ?></p>
            </section>
        <?php endif; ?>
        <section class="wf-card">
            <div class="wf-card-head"><h3>Milestones</h3></div>
            <?php if ($milestones === []): ?>
                <p class="text-muted mb-0">No milestones yet.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($milestones as $milestone): ?>
                        <li class="mb-3">
                            <strong><?= e($milestone->name) ?></strong>
                            <div class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $milestone->status))) ?> · Due <?= e(format_date($milestone->due_date)) ?></div>
                            <?php \App\Core\View::component('progress-meter', ['percent' => (int) $milestone->progress]); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Deliverables</h3></div>
            <?php if ($deliverables === []): ?>
                <p class="text-muted mb-0">Shared deliverables will appear here.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($deliverables as $item): ?>
                        <li class="mb-2">
                            <a href="<?= e(base_url('deliverables/' . $item->id)) ?>"><?= e($item->name) ?></a>
                            <div class="text-muted small"><?= e(ucwords(str_replace('_', ' ', $item->status))) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
