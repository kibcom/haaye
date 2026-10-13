<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Deliverables</p>
        <h2>Statuses</h2>
        <p class="text-muted mb-0">These names apply only to this organization. Renaming a status keeps its existing deliverables.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card">
            <?php foreach ($statuses as $status): ?>
                <form class="wf-status-row" method="post" action="<?= e(base_url('deliverables/statuses/' . $status->id)) ?>">
                    <?= csrf_field() ?>
                    <input class="form-control" type="color" name="color" value="<?= e(strtolower($status->color)) ?>" aria-label="Color for <?= e($status->name) ?>">
                    <input class="form-control" name="name" value="<?= e($status->name) ?>" required>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) $status->sort_order ?>" aria-label="Order">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_closed" value="1" id="del-closed-<?= (int) $status->id ?>" <?= $status->is_closed ? 'checked' : '' ?>>
                        <label class="form-check-label" for="del-closed-<?= (int) $status->id ?>">Closed</label>
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('check-lg') ?> Save</button>
                </form>
                <form method="post" action="<?= e(base_url('deliverables/statuses/' . $status->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this status?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                </form>
            <?php endforeach; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Add status</h3></div>
            <form method="post" action="<?= e(base_url('deliverables/statuses')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="color">Color</label>
                    <input class="form-control form-control-color" id="color" type="color" name="color" value="#00AFC6">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="sort_order">Order</label>
                    <input class="form-control" id="sort_order" type="number" name="sort_order" value="<?= count($statuses) + 1 ?>" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_closed" value="1" id="is_closed">
                    <label class="form-check-label" for="is_closed">Closed status</label>
                </div>
                <button class="btn btn-primary" type="submit"><?= icon('plus-lg') ?> Add status</button>
            </form>
        </section>
    </div>
</div>
