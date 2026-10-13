<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2>Record expense</h2>
        <p class="text-muted mb-0">New expenses stay pending until someone with approval permission decides them.</p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url('expenses')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-3">
            <?php if ($people): ?>
                <div class="col-md-6">
                    <label class="form-label" for="user_id">Employee</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person->id ?>" <?= (int) $person->id === (int) \App\Core\Auth::id() ? 'selected' : '' ?>><?= e($person->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label" for="category">Category</label>
                <select class="form-select" id="category" name="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category) ?>"><?= e(ucfirst($category)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="amount">Amount</label>
                <input class="form-control" id="amount" name="amount" inputmode="decimal" value="<?= e((string) old('amount')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="spent_on">Date</label>
                <input class="form-control" id="spent_on" type="date" name="spent_on" value="<?= e((string) old('spent_on', date('Y-m-d'))) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="expense-project">Project</label>
                <select class="form-select" id="expense-project" name="project_id">
                    <option value="">No project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project->id ?>"><?= e($project->code . ' · ' . $project->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (can('clients.view')): ?>
                <div class="col-md-6">
                    <label class="form-label" for="client_id">Client</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">No client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client->id ?>"><?= e($client->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">If you choose a project, the client is taken from that project.</div>
                </div>
            <?php endif; ?>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000"><?= e((string) old('description')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="receipt">Receipt</label>
                <input class="form-control" id="receipt" type="file" name="receipt">
                <div class="form-text">Optional. Images, PDF, text, CSV, Office, or ZIP. Up to 8 MB.</div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon('send') ?> Submit expense</button>
            <a class="btn btn-secondary" href="<?= e(base_url('expenses')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
