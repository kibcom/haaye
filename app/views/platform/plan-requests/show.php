<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2><?= e((string) $request['organization_name']) ?></h2>
        <p class="text-muted mb-0"><?= e((string) ($request['company_code'] ?: 'Company')) ?> · sent <?= e(format_date((string) $request['created_at'])) ?></p>
    </div>
    <?php \App\Core\View::component('status-badge', ['status' => (string) $request['status']]); ?>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="wf-card">
            <div class="wf-card-head"><h3><?= icon('credit-card') ?> Payment</h3></div>
            <div class="wf-dl">
                <div><dt>From</dt><dd><?= e((string) $request['current_plan_name']) ?></dd></div>
                <div><dt>To</dt><dd><?= e((string) $request['requested_plan_name']) ?></dd></div>
                <div><dt>Amount</dt><dd><?= e(money((string) $request['currency'], $request['amount'])) ?></dd></div>
                <div><dt>Method</dt><dd><?= e((string) $request['method_label']) ?></dd></div>
                <div><dt>Reference</dt><dd><?= e((string) ($request['reference'] ?: '')) ?></dd></div>
                <div><dt>Sent by</dt><dd><?= e((string) ($request['requester_name'] ?: '')) ?></dd></div>
            </div>
            <?php if (!empty($request['note'])): ?>
                <p class="mb-0"><?= e((string) $request['note']) ?></p>
            <?php endif; ?>
            <?php if ((string) $request['status'] !== 'pending'): ?>
                <p class="text-muted mt-3 mb-0">
                    <?= (string) $request['status'] === 'approved' ? 'Approved' : 'Rejected' ?>
                    <?php if (!empty($request['reviewer_name'])): ?>by <?= e((string) $request['reviewer_name']) ?><?php endif; ?>
                    <?php if (!empty($request['reviewed_at'])): ?> on <?= e(format_date((string) $request['reviewed_at'])) ?><?php endif; ?>
                    <?php if (!empty($request['review_note'])): ?><br><?= e((string) $request['review_note']) ?><?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
        <?php if ($canReview): ?>
            <section class="wf-card mt-4">
                <div class="wf-card-head"><h3><?= icon('check2-circle') ?> Decision</h3></div>
                <form method="post" action="<?= e(base_url('platform/plan-requests/' . $request['id'] . '/approve')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label" for="approve-note">Note</label>
                    <textarea class="form-control mb-3" id="approve-note" name="review_note" rows="2" maxlength="500" placeholder="Optional note for the company"></textarea>
                    <button class="btn btn-success" type="submit" data-confirm="Approve this plan and apply it to the company?"><?= icon('check-lg') ?> Approve upgrade</button>
                </form>
                <form class="mt-3" method="post" action="<?= e(base_url('platform/plan-requests/' . $request['id'] . '/reject')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label" for="reject-note">Reason</label>
                    <textarea class="form-control mb-3 <?= error('review_note') ? 'is-invalid' : '' ?>" id="reject-note" name="review_note" rows="2" maxlength="500" required placeholder="Why this payment was not accepted"></textarea>
                    <button class="btn btn-danger" type="submit" data-confirm="Reject this plan request?"><?= icon('x-circle') ?> Reject</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
    <div class="col-lg-7">
        <section class="wf-card">
            <div class="wf-card-head"><h3><?= icon('image') ?> Screenshot</h3></div>
            <a href="<?= e(asset((string) $request['screenshot'])) ?>" target="_blank" rel="noopener">
                <img class="wf-plan-shot" src="<?= e(asset((string) $request['screenshot'])) ?>" alt="Payment screenshot from <?= e((string) $request['organization_name']) ?>">
            </a>
        </section>
    </div>
</div>
