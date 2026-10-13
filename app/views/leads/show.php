<div class="wf-page-header">
    <div>
        <p class="wf-kicker">CRM</p>
        <h2><?= e($lead->name) ?></h2>
        <p class="text-muted mb-0"><?= e($lead->company ?: 'No company yet') ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($canEdit)): ?>
            <a class="btn btn-soft-primary" href="<?= e(base_url('leads/' . $lead->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
        <?php endif; ?>
        <?php if (can('leads.update')): ?>
            <form method="post" action="<?= e(base_url('leads/' . $lead->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this lead?">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit"><?= icon('trash3') ?> Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Lead</h3></div>
            <dl class="wf-dl">
                <div>
                    <dt>Stage</dt>
                    <dd>
                        <?php \App\Core\View::component('status-badge', ['status' => $lead->status]); ?>
                        <?php if (can('leads.update') && $lead->status !== 'converted'): ?>
                            <form class="wf-stage-form" method="post" action="<?= e(base_url('leads/' . $lead->id . '/stage')) ?>">
                                <?= csrf_field() ?>
                                <label class="visually-hidden" for="stage">Stage</label>
                                <select class="form-select form-select-sm" id="stage" name="status">
                                    <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'lost' => 'Lost'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= selected($lead->status, $value) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary btn-sm" type="submit"><?= icon('check-lg') ?> Update stage</button>
                            </form>
                        <?php endif; ?>
                    </dd>
                </div>
                <div><dt>Source</dt><dd><?= e(ucfirst($lead->source)) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($lead->email ?: '') ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($lead->phone ?: '') ?></dd></div>
                <div><dt>Estimated value</dt><dd><?= $lead->estimated_value !== null && $lead->estimated_value !== '' ? e(money($currency, $lead->estimated_value)) : '' ?></dd></div>
                <div><dt>Owner</dt><dd><?= e($lead->owner_name ?: 'Unassigned') ?></dd></div>
                <div><dt>Added</dt><dd><?= e(format_datetime($lead->created_at)) ?></dd></div>
            </dl>
            <?php if ($lead->notes): ?>
                <p class="mt-3 mb-0"><?= nl2br(e($lead->notes)) ?></p>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Client and project</h3></div>
            <?php if ($lead->client_id): ?>
                <p class="mb-2">This lead is a client<?php if (can('clients.view')): ?>: <a href="<?= e(base_url('clients/' . $lead->client_id)) ?>"><?= e($lead->client_name ?: 'Open client') ?></a><?php endif; ?>.</p>
                <?php if ($lead->project_id && (can('projects.view') || can('projects.view_all'))): ?>
                    <p class="mb-0">Project: <a href="<?= e(base_url('projects/' . $lead->project_id)) ?>"><?= e($lead->project_name ?: 'Open project') ?></a></p>
                <?php elseif (!$lead->project_id): ?>
                    <p class="text-muted mb-0">No project was opened from this lead.</p>
                <?php endif; ?>
            <?php elseif (!empty($canConvert)): ?>
                <p class="text-muted">Create the client from this lead. You can open the delivery project at the same time.</p>
                <form method="post" action="<?= e(base_url('leads/' . $lead->id . '/convert')) ?>">
                    <?= csrf_field() ?>
                    <?php if (!empty($canOpenProject)): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="open_project" name="open_project" value="1" checked>
                            <label class="form-check-label" for="open_project">Open a project</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="project_name">Project name</label>
                            <input class="form-control" id="project_name" name="project_name" value="<?= e($lead->company ?: $lead->name) ?>">
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit"><?= icon('arrow-right') ?> Convert to client</button>
                </form>
            <?php else: ?>
                <p class="text-muted mb-0">This lead is still in the pipeline.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
