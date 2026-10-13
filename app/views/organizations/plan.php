<?php
/** @var array<string, mixed>|null $pending */
/** @var array<string, mixed>|null $latest */
$showForm = $pending === null;
$planLine = static function (array $plan) use ($organization): string {
    if ($plan['price'] !== null && (float) $plan['price'] > 0) {
        $price = money($organization->currency, $plan['price']) . ' per month';
    } elseif ($plan['price'] !== null) {
        $price = 'Free';
    } else {
        $price = 'Price set by the platform';
    }
    $workspaces = $plan['workspaces'] === null
        ? 'Unlimited workspaces'
        : ((int) $plan['workspaces'] === 1 ? '1 workspace' : (int) $plan['workspaces'] . ' workspaces');

    return $price . ' · ' . $workspaces . ' · ' . (int) $plan['features'] . ' functions';
};
?>
<?php \App\Core\View::component('settings-nav', []); ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Settings</p>
        <h2>Update plan</h2>
        <p class="text-muted mb-0">Pay for another plan, then send the receipt for approval.</p>
    </div>
</div>

<section class="wf-card mb-4">
    <div class="wf-card-head">
        <h3><?= icon('credit-card') ?> Current plan</h3>
        <?php \App\Core\View::component('status-badge', ['status' => 'active', 'label' => 'Current']); ?>
    </div>
    <div class="wf-plan-choice is-current">
        <span>
            <strong><?= e($currentPlan['name']) ?></strong>
            <small><?= e($currentPlan['code']) ?> · <?= e($planLine($currentPlan)) ?></small>
        </span>
    </div>
</section>

<?php if ($pending !== null): ?>
    <section class="wf-card mb-4">
        <div class="wf-card-head">
            <h3><?= icon('hourglass-split') ?> Waiting for approval</h3>
            <?php \App\Core\View::component('status-badge', ['status' => 'pending']); ?>
        </div>
        <p>A request to move from <strong><?= e((string) $pending['current_plan_name']) ?></strong> to <strong><?= e((string) $pending['requested_plan_name']) ?></strong> was sent <?= e(format_date((string) $pending['created_at'])) ?>.</p>
        <div class="wf-dl">
            <div><dt>Amount</dt><dd><?= e(money((string) $pending['currency'], $pending['amount'])) ?></dd></div>
            <div><dt>Method</dt><dd><?= e((string) $pending['method_label']) ?></dd></div>
            <div><dt>Reference</dt><dd><?= e((string) ($pending['reference'] ?: '')) ?></dd></div>
        </div>
        <?php if (!empty($pending['note'])): ?>
            <p class="mb-2"><?= e((string) $pending['note']) ?></p>
        <?php endif; ?>
        <img class="wf-plan-shot" src="<?= e(asset((string) $pending['screenshot'])) ?>" alt="Payment screenshot">
    </section>
<?php elseif ($latest !== null && (string) $latest['status'] === 'rejected'): ?>
    <section class="wf-card mb-4">
        <div class="wf-card-head">
            <h3><?= icon('x-circle') ?> Last request was rejected</h3>
            <?php \App\Core\View::component('status-badge', ['status' => 'rejected']); ?>
        </div>
        <p class="mb-0"><?= e((string) ($latest['review_note'] ?: 'The platform owner did not approve the last payment. You can send a new request.')) ?></p>
    </section>
<?php endif; ?>

<?php if ($showForm): ?>
<section class="wf-card">
    <?php if ($choices === []): ?>
        <p class="mb-0">There is no other plan to move to.</p>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(base_url('settings/organization/plan')) ?>">
            <?= csrf_field() ?>
            <fieldset class="mb-4">
                <legend class="form-label">Plan</legend>
                <div class="wf-plan-choices">
                    <?php foreach ($choices as $choice): ?>
                        <?php $checked = (string) old('plan') === $choice['code']; ?>
                        <label class="wf-plan-choice">
                            <input type="radio" name="plan" value="<?= e($choice['code']) ?>" <?= $checked ? 'checked' : '' ?> required>
                            <span>
                                <strong><?= e($choice['name']) ?></strong>
                                <small><?= e($planLine($choice)) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (error('plan')): ?><div class="invalid-feedback d-block"><?= e((string) error('plan')) ?></div><?php endif; ?>
            </fieldset>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="method">Payment method</label>
                    <select class="form-select <?= error('method') ? 'is-invalid' : '' ?>" id="method" name="method" required>
                        <option value="">Select a method</option>
                        <option value="bank" <?= selected((string) old('method'), 'bank') ?>>Bank transfer</option>
                        <option value="card" <?= selected((string) old('method'), 'card') ?>>Card</option>
                        <option value="mobile" <?= selected((string) old('method'), 'mobile') ?>>Mobile money</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="reference">Payment reference</label>
                    <input class="form-control <?= error('reference') ? 'is-invalid' : '' ?>" id="reference" name="reference" maxlength="80" value="<?= e((string) old('reference')) ?>" placeholder="Receipt or transfer number">
                </div>
                <div class="col-12">
                    <label class="form-label" for="note">Note</label>
                    <textarea class="form-control" id="note" name="note" rows="2" maxlength="500"><?= e((string) old('note')) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="screenshot">Payment screenshot</label>
                    <input class="form-control" id="screenshot" type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" required>
                    <div class="form-text">JPG, PNG, or WEBP. Up to 2 MB. This is the receipt the platform owner checks before approving the plan.</div>
                    <img class="wf-plan-shot" id="screenshot-preview" alt="" hidden>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= icon('send') ?> Submit payment</button>
                <a class="btn btn-secondary" href="<?= e(base_url('settings/organization')) ?>"><?= icon('x-lg') ?> Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>
<script>
(function () {
    var input = document.getElementById('screenshot');
    var preview = document.getElementById('screenshot-preview');
    if (!input || !preview) return;
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) {
            preview.hidden = true;
            preview.removeAttribute('src');
            return;
        }
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
    });
})();
</script>
<?php endif; ?>
