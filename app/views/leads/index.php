<?php
$labels = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'converted' => 'Converted', 'lost' => 'Lost'];
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">CRM</p>
        <h2>Leads</h2>
        <p class="text-muted mb-0">
            <?php if (!empty($scopedToSelf)): ?>
                Leads you own. Ask an admin for <strong>View all leads</strong> on your role to see the full pipeline.
            <?php else: ?>
                People and companies that are not clients yet. Convert a qualified lead into a client and a project.
            <?php endif; ?>
        </p>
    </div>
    <?php if (can('leads.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('leads/create')) ?>"><?= icon('plus-lg') ?> New lead</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($labels as $value => $label): ?>
        <div class="col-6 col-md">
            <a class="wf-stat" href="<?= e(base_url('leads?status=' . $value)) ?>">
                <span><?= e($label) ?></span>
                <strong><?= (int) ($counts[$value] ?? 0) ?></strong>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<form class="wf-filters" method="get">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search name, company, email, or phone">
    <select class="form-select" name="status">
        <option value="">All stages</option>
        <?php foreach ($labels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= selected($status ?? '', $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($leads)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No leads yet',
            'message' => 'Add the first inquiry. When it is ready, turn it into a client and open a project.',
            'actionUrl' => can('leads.create') ? 'leads/create' : null,
            'actionLabel' => can('leads.create') ? 'New lead' : null,
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Lead</th>
                    <th>Source</th>
                    <th>Stage</th>
                    <th>Value</th>
                    <th>Owner</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($leads as $lead): ?>
                    <?php
                    $leadUrl = 'leads/' . $lead->id;
                    if ($lead->status === 'converted' && (int) $lead->client_id > 0 && can('clients.view')) {
                        $leadUrl = 'clients/' . (int) $lead->client_id;
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url($leadUrl)) ?>"><strong><?= e($lead->name) ?></strong></a>
                            <div class="text-muted small"><?= e($lead->company ?: $lead->email ?: '') ?></div>
                        </td>
                        <td><?= e(ucfirst($lead->source)) ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $lead->status]); ?></td>
                        <td><?= $lead->estimated_value !== null && $lead->estimated_value !== '' ? e(money($currency, $lead->estimated_value)) : '' ?></td>
                        <td><?= e($lead->owner_name ?: 'Unassigned') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
