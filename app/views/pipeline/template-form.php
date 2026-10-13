<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Pipeline</p>
        <h2>New workflow template</h2>
        <p class="text-muted mb-0">Define progression mode, then add stages on the next screen.</p>
    </div>
</div>

<section class="wf-card" style="max-width:640px">
    <form method="post" action="<?= e(base_url('pipeline/templates')) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" required maxlength="160" value="<?= e((string) old('name')) ?>" placeholder="e.g. Marketing Campaign Pipeline">
        </div>
        <div class="mb-3">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= e((string) old('description')) ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="progression_mode">Progression mode</label>
            <select class="form-select" id="progression_mode" name="progression_mode">
                <option value="automatic" <?= selected(old('progression_mode', 'hybrid'), 'automatic') ?>>Automatic — next stage after approval</option>
                <option value="manual" <?= selected(old('progression_mode', 'hybrid'), 'manual') ?>>Manual — manager confirms each next stage</option>
                <option value="hybrid" <?= selected(old('progression_mode', 'hybrid'), 'hybrid') ?>>Hybrid — per-stage auto-advance flag</option>
            </select>
        </div>
        <label class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
            <span class="form-check-label">Active</span>
        </label>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon('plus-lg') ?> Create template</button>
            <a class="btn btn-secondary" href="<?= e(base_url('pipeline')) ?>">Cancel</a>
        </div>
    </form>
</section>
