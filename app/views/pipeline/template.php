<?php
$modes = ['automatic' => 'Automatic', 'manual' => 'Manual', 'hybrid' => 'Hybrid'];
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Pipeline template</p>
        <h2><?= e($pipelineTemplate->name) ?></h2>
        <p class="text-muted mb-0"><?= e($pipelineTemplate->description ?: 'Add and order stages. Start this template from a project.') ?></p>
    </div>
    <a class="btn btn-secondary" href="<?= e(base_url('pipeline')) ?>">Back</a>
</div>

<?php if (!empty($canManage)): ?>
<section class="wf-card mb-3">
    <div class="wf-card-head"><h3>Template settings</h3></div>
    <form method="post" action="<?= e(base_url('pipeline/templates/' . $pipelineTemplate->id)) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label" for="name">Name</label>
            <input class="form-control form-control-sm" id="name" name="name" value="<?= e($pipelineTemplate->name) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="progression_mode">Mode</label>
            <select class="form-select form-select-sm" id="progression_mode" name="progression_mode">
                <?php foreach ($modes as $slug => $label): ?>
                    <option value="<?= e($slug) ?>" <?= selected($pipelineTemplate->progression_mode, $slug) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="description">Description</label>
            <input class="form-control form-control-sm" id="description" name="description" value="<?= e((string) $pipelineTemplate->description) ?>">
        </div>
        <div class="col-md-2 d-flex align-items-end gap-2">
            <label class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int) $pipelineTemplate->is_active ? 'checked' : '' ?>>
                <span class="form-check-label">Active</span>
            </label>
            <button class="btn btn-sm btn-primary mb-2" type="submit">Save</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="wf-card mb-3">
    <div class="wf-card-head"><h3>Stages</h3></div>
    <?php if (empty($stages)): ?>
        <p class="text-muted">No stages yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr><th>#</th><th>Stage</th><th>Role / assignee</th><th>Approval</th><th>Auto</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($stages as $stage): ?>
                    <tr class="<?= !(int) $stage->is_active ? 'opacity-50' : '' ?>">
                        <td><?= (int) $stage->sort_order ?></td>
                        <td>
                            <strong><?= e($stage->name) ?></strong>
                            <?php if ($stage->instructions): ?>
                                <div class="text-muted small"><?= e(mb_strimwidth((string) $stage->instructions, 0, 80, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($stage->role_name ?: '—') ?>
                            <?php if ($stage->assignee_name): ?><div class="small"><?= e($stage->assignee_name) ?></div><?php endif; ?>
                        </td>
                        <td><?= (int) $stage->requires_approval ? 'Yes' : 'No' ?></td>
                        <td><?= (int) $stage->auto_advance ? 'Yes' : 'No' ?></td>
                        <td class="text-end">
                            <?php if (!empty($canManage)): ?>
                                <button class="btn btn-sm btn-outline" type="button" data-bs-toggle="collapse" data-bs-target="#edit-stage-<?= (int) $stage->id ?>">Edit</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($canManage)): ?>
                        <tr class="collapse" id="edit-stage-<?= (int) $stage->id ?>">
                            <td colspan="6">
                                <form method="post" action="<?= e(base_url('pipeline/templates/' . $pipelineTemplate->id . '/stages/' . $stage->id)) ?>" class="row g-2">
                                    <?= csrf_field() ?>
                                    <div class="col-md-3">
                                        <label class="form-label">Name</label>
                                        <input class="form-control form-control-sm" name="name" value="<?= e($stage->name) ?>" required>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Order</label>
                                        <input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int) $stage->sort_order ?>" min="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Department role</label>
                                        <select class="form-select form-select-sm" name="work_flow_role_id">
                                            <option value="">Any</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?= (int) $role->id ?>" <?= selected($stage->work_flow_role_id, $role->id) ?>><?= e($role->name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Default assignee</label>
                                        <select class="form-select form-select-sm" name="default_assignee_user_id">
                                            <option value="">Unassigned</option>
                                            <?php foreach ($people as $person): ?>
                                                <option value="<?= (int) $person->id ?>" <?= selected($stage->default_assignee_user_id, $person->id) ?>><?= e($person->name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Approver</label>
                                        <select class="form-select form-select-sm" name="approver_user_id">
                                            <option value="">Project manager</option>
                                            <?php foreach ($people as $person): ?>
                                                <option value="<?= (int) $person->id ?>" <?= selected($stage->approver_user_id, $person->id) ?>><?= e($person->name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Instructions</label>
                                        <textarea class="form-control form-control-sm" name="instructions" rows="2"><?= e((string) $stage->instructions) ?></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Required deliverables</label>
                                        <textarea class="form-control form-control-sm" name="deliverables" rows="2"><?= e((string) $stage->deliverables_json) ?></textarea>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end gap-3 flex-wrap">
                                        <label class="form-check"><input class="form-check-input" type="checkbox" name="requires_approval" value="1" <?= (int) $stage->requires_approval ? 'checked' : '' ?>><span class="form-check-label">Needs approval</span></label>
                                        <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_advance" value="1" <?= (int) $stage->auto_advance ? 'checked' : '' ?>><span class="form-check-label">Auto-advance</span></label>
                                        <label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int) $stage->is_active ? 'checked' : '' ?>><span class="form-check-label">Active</span></label>
                                        <button class="btn btn-sm btn-primary" type="submit">Save stage</button>
                                        <button class="btn btn-sm btn-soft-danger" type="submit" formaction="<?= e(base_url('pipeline/templates/' . $pipelineTemplate->id . '/stages/' . $stage->id . '/delete')) ?>" data-confirm="Remove this stage?">Remove</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
<section class="wf-card">
    <div class="wf-card-head"><h3>Add stage</h3></div>
    <form method="post" action="<?= e(base_url('pipeline/templates/' . $pipelineTemplate->id . '/stages')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-3">
            <label class="form-label" for="stage_name">Name</label>
            <input class="form-control form-control-sm" id="stage_name" name="name" required placeholder="e.g. Site Location">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="stage_role">Department role</label>
            <select class="form-select form-select-sm" id="stage_role" name="work_flow_role_id">
                <option value="">Any</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role->id ?>"><?= e($role->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="stage_assignee">Default assignee</label>
            <select class="form-select form-select-sm" id="stage_assignee" name="default_assignee_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($people as $person): ?>
                    <option value="<?= (int) $person->id ?>"><?= e($person->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="stage_approver">Approver</label>
            <select class="form-select form-select-sm" id="stage_approver" name="approver_user_id">
                <option value="">Project manager</option>
                <?php foreach ($people as $person): ?>
                    <option value="<?= (int) $person->id ?>"><?= e($person->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="stage_instructions">Instructions</label>
            <textarea class="form-control form-control-sm" id="stage_instructions" name="instructions" rows="2"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="stage_deliverables">Required deliverables</label>
            <textarea class="form-control form-control-sm" id="stage_deliverables" name="deliverables" rows="2" placeholder="Photos, notes, links…"></textarea>
        </div>
        <div class="col-12 d-flex gap-3 align-items-center">
            <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="requires_approval" value="1" checked><span class="form-check-label">Needs approval</span></label>
            <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="auto_advance" value="1" checked><span class="form-check-label">Auto-advance (hybrid)</span></label>
            <button class="btn btn-sm btn-aqua" type="submit"><?= icon('plus-lg') ?> Add stage</button>
        </div>
    </form>
</section>
<?php endif; ?>
