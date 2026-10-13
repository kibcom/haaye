<div class="wf-page-header">
    <div>
        <p class="wf-kicker">CRM</p>
        <h2>Clients</h2>
        <p class="text-muted mb-0">
            <?php if (!empty($scopedToSelf)): ?>
                Companies assigned to you. Ask an admin for <strong>View all clients</strong> on your role to see everyone.
            <?php else: ?>
                Companies and contacts your organization works with.
            <?php endif; ?>
        </p>
    </div>
    <?php if (can('clients.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('clients/create')) ?>"><?= icon('plus-lg') ?> Add client</a>
    <?php endif; ?>
</div>

<form class="wf-filters" method="get">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search name, email, or contact">
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <?php foreach (['lead' => 'Lead', 'active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= selected($status ?? '', $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($canFilterOwners)): ?>
    <select class="form-select" name="assigned">
        <option value="">All owners</option>
        <?php foreach ($assignees as $person): ?>
            <option value="<?= (int) $person->id ?>" <?= selected($assigned ?? '', $person->id) ?>><?= e($person->name) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
</form>

<section class="wf-card">
    <?php if (empty($clients)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No clients found',
            'message' => 'Add the first client to start tracking contacts and projects.',
            'actionUrl' => can('clients.create') ? 'clients/create' : null,
            'actionLabel' => can('clients.create') ? 'Add client' : null,
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Assigned to</th>
                    <th>Projects</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($clients as $item): ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url('clients/' . $item->id)) ?>"><strong><?= e($item->name) ?></strong></a>
                            <div class="text-muted small"><?= e($item->industry ?: $item->email ?: '') ?></div>
                        </td>
                        <td>
                            <?= e($item->contact_name ?: '') ?>
                            <div class="text-muted small"><?= e($item->email ?: '') ?></div>
                        </td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $item->status]); ?></td>
                        <td><?= e($item->assigned_user_name ?: 'Unassigned') ?></td>
                        <td><?= (int) ($item->project_count ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
</section>
