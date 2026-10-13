<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Delivery</p>
        <h2>Projects</h2>
        <p class="text-muted mb-0">Work delivered for your clients.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('projects.statuses.manage')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('projects/statuses')) ?>"><?= icon('sliders') ?> Statuses</a>
        <?php endif; ?>
        <?php if (can('projects.create')): ?>
            <a class="btn btn-aqua" href="<?= e(base_url('projects/create')) ?>"><?= icon('plus-lg') ?> New project</a>
        <?php endif; ?>
    </div>
</div>

<form class="wf-filters" method="get">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search name or code">
    <select class="form-select" name="client_id">
        <option value="">All clients</option>
        <?php foreach ($clients as $client): ?>
            <option value="<?= (int) $client->id ?>" <?= selected($clientId ?? '', $client->id) ?>><?= e($client->name) ?></option>
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
    <select class="form-select" name="payment_status">
        <option value="">All payment statuses</option>
        <?php foreach (['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'not_required' => 'Not required'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= selected($paymentStatus ?? '', $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($projects)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No projects found',
            'message' => 'Create a project when a client engagement is ready to start.',
            'actionUrl' => can('projects.create') ? 'projects/create' : null,
            'actionLabel' => can('projects.create') ? 'New project' : null,
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Priority</th>
                    <th>Due date</th>
                    <th>Progress</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url('projects/' . $project->id)) ?>"><strong><?= e($project->name) ?></strong></a>
                            <div class="text-muted small"><?= e($project->code ?: '') ?></div>
                        </td>
                        <td><?= e($project->client_name ?: '') ?></td>
                        <td><?php \App\Core\View::component('status-badge', [
                            'status' => $project->status,
                            'label' => $project->status_name ?: null,
                            'color' => $project->status_color ?: null,
                        ]); ?></td>
                        <td><?php \App\Core\View::component('status-badge', [
                            'status' => $project->payment_status,
                            'label' => ['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'not_required' => 'Not required'][$project->payment_status] ?? null,
                        ]); ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $project->priority]); ?></td>
                        <td><?= e(format_date($project->due_date)) ?></td>
                        <td><?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</section>
