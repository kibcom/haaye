<?php $editing = $client !== null; ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Clients</p>
        <h2><?= $editing ? 'Edit client' : 'Add client' ?></h2>
        <p class="text-muted mb-0"><?= $editing ? 'Update this company record.' : 'Create a client company in this organization only.' ?></p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($editing ? 'clients/' . $client->id : 'clients')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Company / client name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $client->name ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="industry">Industry</label>
                <input class="form-control" id="industry" name="industry" value="<?= e((string) old('industry', $client->industry ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contact_name">Contact person</label>
                <input class="form-control" id="contact_name" name="contact_name" value="<?= e((string) old('contact_name', $client->contact_name ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email', $client->email ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $client->phone ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="website">Website</label>
                <input class="form-control" id="website" name="website" value="<?= e((string) old('website', $client->website ?? '')) ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="address">Address</label>
                <input class="form-control" id="address" name="address" value="<?= e((string) old('address', $client->address ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="city">City</label>
                <input class="form-control" id="city" name="city" value="<?= e((string) old('city', $client->city ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="country">Country</label>
                <input class="form-control" id="country" name="country" value="<?= e((string) old('country', $client->country ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="tax_id">Tax ID</label>
                <input class="form-control" id="tax_id" name="tax_id" value="<?= e((string) old('tax_id', $client->tax_id ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (['lead' => 'Lead', 'active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('status', $client->status ?? 'lead'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="assigned_user_id">Assigned employee</label>
                <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($assignees as $person): ?>
                        <option value="<?= (int) $person->id ?>" <?= selected(old('assigned_user_id', $client->assigned_user_id ?? ''), $person->id) ?>><?= e($person->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="tax_information">Tax information</label>
                <textarea class="form-control" id="tax_information" name="tax_information" rows="2"><?= e((string) old('tax_information', $client->tax_information ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"><?= e((string) old('notes', $client->notes ?? '')) ?></textarea>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'person-plus') ?> <?= $editing ? 'Save client' : 'Add client' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url($editing ? 'clients/' . $client->id : 'clients')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
