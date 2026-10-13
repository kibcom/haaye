<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Delivery</p>
        <h2>Deliverables</h2>
        <p class="text-muted mb-0">Files and versions waiting on internal or client approval.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('deliverables.statuses.manage')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('deliverables/statuses')) ?>"><?= icon('sliders') ?> Statuses</a>
        <?php endif; ?>
        <?php if (can('deliverables.create')): ?>
            <a class="btn btn-aqua" href="<?= e(base_url('deliverables/create')) ?>"><?= icon('plus-lg') ?> New deliverable</a>
        <?php endif; ?>
    </div>
</div>

<form class="wf-filters" method="get">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search deliverables">
    <?php if (!empty($projects)): ?>
        <select class="form-select" name="project_id">
            <option value="">All projects</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= (int) $project->id ?>" <?= selected($projectId ?? '', $project->id) ?>><?= e($project->name) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $item): ?>
            <option value="<?= e($item->slug) ?>" <?= selected($status ?? '', $item->slug) ?>><?= e($item->name) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($deliverables)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No deliverables found',
            'message' => 'Add a deliverable when work is ready for review.',
            'actionUrl' => can('deliverables.create') ? 'deliverables/create' : null,
            'actionLabel' => can('deliverables.create') ? 'New deliverable' : null,
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Deliverable</th>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Version</th>
                    <th>Client approval</th>
                    <th>Due</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($deliverables as $item): ?>
                    <tr>
                        <td><a href="<?= e(base_url('deliverables/' . $item->id)) ?>"><strong><?= e($item->name) ?></strong></a></td>
                        <td><?= e($item->project_name ?: '') ?></td>
                        <td><?php \App\Core\View::component('status-badge', [
                            'status' => $item->status,
                            'label' => $item->status_name ?: null,
                            'color' => $item->status_color ?: null,
                        ]); ?></td>
                        <td>v<?= (int) $item->version ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $item->client_approval_status === 'none' ? 'not_required' : $item->client_approval_status]); ?></td>
                        <td><?= e(format_date($item->due_date)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</section>
