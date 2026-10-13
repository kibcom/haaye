<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Contacts</p>
        <h2>Edit contact</h2>
        <p class="text-muted mb-0">Update this person at <?= e($client->name) ?>.</p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url('clients/' . $client->id . '/contacts/' . $contact->id)) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $contact->name)) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="job_title">Job title</label>
                <input class="form-control" id="job_title" name="job_title" value="<?= e((string) old('job_title', $contact->job_title)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email', $contact->email)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $contact->phone)) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <input class="form-control" id="notes" name="notes" value="<?= e((string) old('notes', $contact->notes)) ?>">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_primary" name="is_primary" <?= checked(old('is_primary', $contact->is_primary), 1) ?>>
                    <label class="form-check-label" for="is_primary">Primary contact</label>
                </div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save contact</button>
            <a class="btn btn-secondary" href="<?= e(base_url('clients/' . $client->id)) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
