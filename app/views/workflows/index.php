<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Workflow</p>
        <h2>Workflows</h2>
        <p class="text-muted mb-0">Until you add a transition, status changes for that record type stay open. After the first transition, only the moves you list are allowed.</p>
    </div>
</div>

<div class="wf-view-toggle btn-group mb-3">
    <?php foreach ($types as $slug => $label): ?>
        <a class="btn btn-sm <?= $type === $slug ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url('workflows?type=' . $slug)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="<?= $canManage ? 'col-lg-7' : 'col-12' ?>">
        <section class="wf-card">
            <div class="wf-card-head"><h3><?= e($types[$type]) ?></h3></div>
            <?php if (!$restricted): ?>
                <?php \App\Core\View::component('empty-state', [
                    'title' => 'No transitions yet',
                    'message' => 'People can still move these records to any status. Add a transition when you want to control the path.',
                ]); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table wf-table align-middle">
                        <thead>
                        <tr><th>From</th><th>To</th><th>Approval</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($transitions as $transition): ?>
                            <tr>
                                <td><?= e(ucwords(str_replace('_', ' ', $transition->from_status))) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', $transition->to_status))) ?></td>
                                <td><?= e($transition->requires_approval === 'none' ? 'None' : ucfirst($transition->requires_approval)) ?></td>
                                <td class="text-end">
                                    <?php if ($canManage): ?>
                                        <form method="post" action="<?= e(base_url('workflows/' . $transition->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this transition?">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <?php if ($canManage): ?>
        <div class="col-lg-5">
            <section class="wf-card">
                <div class="wf-card-head"><h3>Add transition</h3></div>
                <form method="post" action="<?= e(base_url('workflows')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="entity_type" value="<?= e($type) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="from_status">From</label>
                        <select class="form-select" id="from_status" name="from_status" required>
                            <?php foreach ($statuses as $item): ?>
                                <option value="<?= e($item['slug']) ?>"><?= e($item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="to_status">To</label>
                        <select class="form-select" id="to_status" name="to_status" required>
                            <?php foreach ($statuses as $item): ?>
                                <option value="<?= e($item['slug']) ?>"><?= e($item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="requires_approval">Required approval</label>
                        <select class="form-select" id="requires_approval" name="requires_approval">
                            <option value="none">No approval</option>
                            <option value="internal">Internal approval</option>
                            <option value="client">Client approval</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= icon('arrow-left-right') ?> Add transition</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
