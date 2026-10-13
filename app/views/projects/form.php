<?php
$editing = $project !== null;
$prefillClient = (int) ($prefillClient ?? 0);
$selectedClientId = (int) old('client_id', $editing ? (int) ($project->client_id ?? 0) : $prefillClient);
$lockedClient = !$editing && $prefillClient > 0 && !empty($prefillClientModel);
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Projects</p>
        <h2><?= $editing ? 'Edit project' : 'New project' ?></h2>
        <p class="text-muted mb-0">
            <?php if ($lockedClient): ?>
                Creating a project for <strong><?= e($prefillClientModel->name) ?></strong>.
            <?php else: ?>
                A project belongs to one client in this organization.
            <?php endif; ?>
        </p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($editing ? 'projects/' . $project->id : 'projects')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Project name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $editing ? (string) ($project->name ?? '') : '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="client_id">Client</label>
                <?php if ($lockedClient): ?>
                    <input type="hidden" name="client_id" value="<?= (int) $prefillClientModel->id ?>">
                    <input class="form-control" id="client_id" type="text" value="<?= e($prefillClientModel->name) ?>" readonly>
                <?php else: ?>
                    <select class="form-select" id="client_id" name="client_id" required>
                        <option value="">Select a client</option>
                        <?php
                        $clientIds = [];
                        foreach ($clients as $client):
                            $clientIds[] = (int) $client->id;
                            ?>
                            <option value="<?= (int) $client->id ?>" <?= selected($selectedClientId, $client->id) ?>><?= e($client->name) ?></option>
                        <?php endforeach; ?>
                        <?php if ($selectedClientId > 0 && !in_array($selectedClientId, $clientIds, true) && !empty($prefillClientModel)): ?>
                            <option value="<?= (int) $prefillClientModel->id ?>" selected><?= e($prefillClientModel->name) ?></option>
                        <?php endif; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="code">Project code</label>
                <input class="form-control" id="code" name="code" value="<?= e((string) old('code', $project->code ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach ($statuses as $item): ?>
                        <option value="<?= e($item->slug) ?>" <?= selected(old('status', $project->status ?? 'planning'), $item->slug) ?>><?= e($item->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="priority">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('priority', $project->priority ?? 'medium'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="start_date">Start date</label>
                <input class="form-control" id="start_date" type="date" name="start_date" value="<?= e((string) old('start_date', $project->start_date ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="due_date">Due date</label>
                <input class="form-control" id="due_date" type="date" name="due_date" value="<?= e((string) old('due_date', $project->due_date ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <span class="form-label">Progress</span>
                <?php if ($editing): ?>
                    <?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?>
                <?php else: ?>
                    <?php \App\Core\View::component('progress-meter', ['percent' => 0, 'caption' => 'No tasks']); ?>
                <?php endif; ?>
                <p class="text-muted small mb-0 mt-1">Updates from tasks created and completed.</p>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="manager_user_id">Project manager</label>
                <select class="form-select" id="manager_user_id" name="manager_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= selected(old('manager_user_id', $project->manager_user_id ?? ''), $person->id) ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="billing_type">Billing type</label>
                <select class="form-select" id="billing_type" name="billing_type">
                    <?php foreach (['fixed' => 'Fixed', 'hourly' => 'Hourly', 'retainer' => 'Retainer', 'non_billable' => 'Non-billable'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('billing_type', $project->billing_type ?? 'fixed'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="budget">Budget</label>
                <input class="form-control" id="budget" type="number" min="0" step="0.01" name="budget" value="<?= e((string) old('budget', $project->budget ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="payment_status">Payment status</label>
                <select class="form-select" id="payment_status" name="payment_status">
                    <?php foreach (['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'not_required' => 'Not required'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('payment_status', $project->payment_status ?? 'unpaid'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?= e((string) old('description', $project->description ?? '')) ?></textarea>
            </div>
        </div>
        <p class="text-muted small mt-3 mb-0">Budget and payment status are planning fields. Invoices are not calculated here.</p>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'plus-lg') ?> <?= $editing ? 'Save project' : 'Create project' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url($editing ? 'projects/' . $project->id : 'projects')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
