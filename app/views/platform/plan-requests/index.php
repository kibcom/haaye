<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2>Plan requests</h2>
        <p class="text-muted mb-0">Check the payment method and screenshot, then approve the plan change.</p>
    </div>
</div>

<section class="wf-card">
    <?php if ($requests === []): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No plan requests',
            'message' => 'Requests appear here when a company submits a payment for a new plan.',
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table wf-keep align-middle mb-0">
                <thead>
                <tr>
                    <th>Company</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Sent</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url('platform/plan-requests/' . $request['id'])) ?>"><strong><?= e((string) $request['organization_name']) ?></strong></a>
                            <div class="text-muted small"><?= e((string) ($request['company_code'] ?: '')) ?></div>
                        </td>
                        <td><?= e((string) $request['current_plan_name']) ?></td>
                        <td><?= e((string) $request['requested_plan_name']) ?></td>
                        <td><?= e(money((string) $request['currency'], $request['amount'])) ?></td>
                        <td><?= e((string) $request['method_label']) ?></td>
                        <td><?= e(format_date((string) $request['created_at'])) ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => (string) $request['status']]); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
