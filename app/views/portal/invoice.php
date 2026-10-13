<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Invoice</p>
        <h2><?= e($invoice->number) ?></h2>
        <p class="text-muted mb-0">Issued <?= e(format_date($invoice->issue_date)) ?> · Due <?= e(format_date($invoice->due_date)) ?></p>
    </div>
    <?php \App\Core\View::component('status-badge', ['status' => $invoice->displayStatus()]); ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card">
            <div class="table-responsive">
                <table class="table wf-table align-middle mb-0">
                    <thead><tr><th>Description</th><th>Qty</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item->description) ?></td>
                                <td><?= e(number_format((float) $item->quantity, 2)) ?></td>
                                <td><?= e(money($invoice->currency, $item->line_total)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <dl class="wf-dl mt-3 mb-0">
                <div><dt>Total</dt><dd><?= e(money($invoice->currency, $invoice->total)) ?></dd></div>
                <div><dt>Paid</dt><dd><?= e(money($invoice->currency, $invoice->paid_amount)) ?></dd></div>
                <div><dt>Balance</dt><dd><?= e(money($invoice->currency, $invoice->balance)) ?></dd></div>
            </dl>
            <?php if ($invoice->notes): ?>
                <p class="mt-3 mb-0"><?= nl2br(e($invoice->notes)) ?></p>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Payments</h3></div>
            <?php if ($payments === []): ?>
                <p class="text-muted mb-0">No payments recorded yet.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($payments as $payment): ?>
                        <li class="mb-2">
                            <strong><?= e(money($invoice->currency, $payment->amount)) ?></strong>
                            <div class="text-muted small"><?= e(format_date($payment->paid_on)) ?> · <?= e(ucfirst($payment->method)) ?><?= $payment->reference ? ' · ' . e($payment->reference) : '' ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="text-muted small mt-3 mb-0">Payment status is updated when your account team records a payment.</p>
        </section>
    </div>
</div>
