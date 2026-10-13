<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2><?= e(ucfirst($expense->category)) ?></h2>
        <p class="text-muted mb-0"><?= e(money($currency, $expense->amount)) ?> · <?= e(format_date($expense->spent_on)) ?></p>
    </div>
    <?php \App\Core\View::component('status-badge', ['status' => $expense->status]); ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card">
            <dl class="wf-dl">
                <div><dt>Employee</dt><dd><?= e($expense->user_name ?: 'Former user') ?></dd></div>
                <div><dt>Project</dt><dd><?= e($expense->project_name ?: '') ?></dd></div>
                <div><dt>Client</dt><dd><?= e($expense->client_name ?: '') ?></dd></div>
                <div><dt>Description</dt><dd><?= $expense->description ? nl2br(e($expense->description)) : '' ?></dd></div>
                <?php if ($expense->stored_path): ?>
                    <div><dt>Receipt</dt><dd><a href="<?= e(base_url('expenses/' . $expense->id . '/receipt')) ?>"><?= e($expense->original_name ?: 'Download') ?></a></dd></div>
                <?php endif; ?>
                <?php if ($expense->decided_at): ?>
                    <div><dt>Decision</dt><dd><?= e($expense->decider_name ?: 'Former user') ?> · <?= e(format_datetime($expense->decided_at)) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($expense->decision_comment): ?>
                <p class="mt-3 mb-0"><?= nl2br(e($expense->decision_comment)) ?></p>
            <?php endif; ?>
            <?php if ($canDelete): ?>
                <form method="post" action="<?= e(base_url('expenses/' . $expense->id . '/delete')) ?>" class="mt-3" data-confirm="Are you sure you want to delete this expense?">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger" type="submit"><?= icon('trash3') ?> Delete</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
    <?php if ($canDecide): ?>
        <div class="col-lg-5">
            <section class="wf-card">
                <div class="wf-card-head"><h3>Decision</h3></div>
                <form method="post" action="<?= e(base_url('expenses/' . $expense->id . '/decide')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label" for="comment">Comment</label>
                    <textarea class="form-control mb-3" id="comment" name="comment" rows="3" maxlength="2000" placeholder="Required when rejecting"></textarea>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success" name="decision" value="approved" type="submit" data-confirm="Are you sure you want to approve this expense?"><?= icon('check-circle') ?> Approve</button>
                        <button class="btn btn-danger" name="decision" value="rejected" type="submit" data-confirm="Are you sure you want to reject this expense?"><?= icon('x-circle') ?> Reject</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>
</div>
