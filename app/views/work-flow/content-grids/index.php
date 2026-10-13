<?php
/** @var list<\App\Models\ContentGrid> $grids */
/** @var list<\App\Models\Client> $clients */
$canManage = !empty($canManage);
$canDelete = !empty($canDelete);
$clientId = (int) ($clientId ?? 0);
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Workflow</p>
        <h2>Monthly Content Grids</h2>
        <p class="text-muted mb-0">One content calendar per company — weeks, post types, platforms, edited &amp; posted status.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline btn-sm" href="<?= e(base_url('work-flow')) ?>"><?= icon('arrow-left') ?> Workflow</a>
        <?php if ($canManage): ?>
            <button class="btn btn-sm btn-aqua" type="button" data-bs-toggle="modal" data-bs-target="#cg-create-modal">
                <?= icon('plus-lg') ?> New grid
            </button>
        <?php endif; ?>
    </div>
</div>

<form class="wf-filters mb-3" method="get">
    <select class="form-select" name="client">
        <option value="">All companies</option>
        <?php foreach ($clients as $client): ?>
            <option value="<?= (int) $client->id ?>" <?= selected($clientId, $client->id) ?>><?= e($client->name) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline btn-sm" type="submit">Filter</button>
</form>

<section class="wf-card">
    <?php if (empty($grids)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No content grids yet',
            'message' => 'Create a monthly content grid for a company to track posts week by week.',
        ]); ?>
    <?php else: ?>
        <ul class="list-group list-group-flush wf-workflow-list">
            <?php foreach ($grids as $grid): ?>
                <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <a class="fw-semibold text-decoration-none" href="<?= e(base_url('work-flow/content-grids/' . $grid->id)) ?>">
                            <?= e($grid->client_name ?: 'Company') ?>
                        </a>
                        <div class="text-muted small">
                            <?= e(format_date($grid->start_date)) ?> → <?= e(format_date($grid->end_date)) ?>
                            · Edited <?= (int) $grid->edited_count ?>/<?= (int) $grid->day_count ?>
                            · Posted <?= (int) $grid->posted_count ?>/<?= (int) $grid->day_count ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <a class="btn btn-sm btn-outline" href="<?= e(base_url('work-flow/content-grids/' . $grid->id)) ?>">
                            <?= icon('grid-3x3') ?> Open
                        </a>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(base_url('work-flow/content-grids/' . $grid->id . '/delete')) ?>" data-confirm="Remove this content grid?">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($canManage): ?>
<div class="modal fade" id="cg-create-modal" tabindex="-1" aria-labelledby="cg-create-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= e(base_url('work-flow/content-grids')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="cg-create-title">New monthly content grid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="cg_client_id">Company</label>
                    <select class="form-select" id="cg_client_id" name="client_id" required>
                        <option value="">Select company</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client->id ?>"><?= e($client->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="cg_start_date">Start date</label>
                        <input class="form-control" id="cg_start_date" type="date" name="start_date" required value="<?= e(date('Y-m-01')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cg_end_date">End date</label>
                        <input class="form-control" id="cg_end_date" type="date" name="end_date" required value="<?= e(date('Y-m-t')) ?>">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label" for="cg_notes">Notes</label>
                    <input class="form-control" id="cg_notes" name="notes" maxlength="255" placeholder="Optional">
                </div>
                <p class="text-muted small mt-3 mb-0">Starts with the standard 4-week template (Reel / Graphics / Sunday rest).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-aqua"><?= icon('plus-lg') ?> Create grid</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
