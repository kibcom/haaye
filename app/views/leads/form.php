<?php
$editing = $lead !== null;
$sources = ['website' => 'Website', 'referral' => 'Referral', 'call' => 'Call', 'email' => 'Email', 'event' => 'Event', 'other' => 'Other'];
$stages = ['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'lost' => 'Lost'];
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">CRM</p>
        <h2><?= $editing ? 'Edit lead' : 'New lead' ?></h2>
        <p class="text-muted mb-0"><?= $editing ? 'Update this inquiry before it becomes a client.' : 'Capture a person or company that is not a client yet.' ?></p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($editing ? 'leads/' . $lead->id : 'leads')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Contact name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $lead->name ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="company">Company</label>
                <input class="form-control" id="company" name="company" value="<?= e((string) old('company', $lead->company ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email', $lead->email ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $lead->phone ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="source">Source</label>
                <select class="form-select" id="source" name="source">
                    <?php foreach ($sources as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('source', $lead->source ?? 'other'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Stage</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach ($stages as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('status', $lead->status ?? 'new'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="estimated_value">Estimated value (<?= e($currency) ?>)</label>
                <input class="form-control" id="estimated_value" name="estimated_value" inputmode="decimal" value="<?= e((string) old('estimated_value', $lead->estimated_value ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="owner_user_id">Owner</label>
                <select class="form-select" id="owner_user_id" name="owner_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($owners as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= selected(old('owner_user_id', $lead->owner_user_id ?? ''), $person->id) ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4"><?= e((string) old('notes', $lead->notes ?? '')) ?></textarea>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'plus-lg') ?> <?= $editing ? 'Save lead' : 'Add lead' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url($editing ? 'leads/' . $lead->id : 'leads')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
