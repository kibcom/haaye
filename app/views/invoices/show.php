<?php $shown = $invoice->displayStatus(); ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2><?= e($invoice->number) ?></h2>
        <p class="text-muted mb-0"><?= e($invoice->client_name) ?><?= $invoice->project_name ? ' · ' . e($invoice->project_name) : '' ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php \App\Core\View::component('status-badge', ['status' => $shown]); ?>
        <?php if ($canManage && $invoice->status === 'draft'): ?>
            <a class="btn btn-soft-primary" href="<?= e(base_url('invoices/' . $invoice->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
            <form method="post" action="<?= e(base_url('invoices/' . $invoice->id . '/send')) ?>" data-confirm="Are you sure you want to send this invoice?">
                <?= csrf_field() ?>
                <button class="btn btn-info" type="submit"><?= icon('send') ?> Mark sent</button>
            </form>
        <?php endif; ?>
        <?php if ($canManage && !in_array($invoice->status, ['paid', 'cancelled'], true) && (float) $invoice->paid_amount <= 0): ?>
            <form method="post" action="<?= e(base_url('invoices/' . $invoice->id . '/cancel')) ?>" data-confirm="Are you sure you want to cancel this invoice?">
                <?= csrf_field() ?>
                <button class="btn btn-soft-danger" type="submit"><?= icon('x-lg') ?> Cancel</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card mb-3">
            <div class="wf-card-head"><h3>Lines</h3></div>
            <div class="table-responsive">
                <table class="table wf-table align-middle mb-0">
                    <thead>
                        <tr><th>Description</th><th>Qty</th><th>Price</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item->description) ?></td>
                                <td><?= e(number_format((float) $item->quantity, 2)) ?></td>
                                <td><?= e(money($invoice->currency, $item->unit_price)) ?></td>
                                <td><?= e(money($invoice->currency, $item->line_total)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <dl class="wf-dl mt-3">
                <div><dt>Subtotal</dt><dd><?= e(money($invoice->currency, $invoice->subtotal)) ?></dd></div>
                <div><dt>Tax (<?= e(number_format((float) $invoice->tax_rate, 2)) ?>%)</dt><dd><?= e(money($invoice->currency, $invoice->tax_amount)) ?></dd></div>
                <div><dt>Discount</dt><dd><?= e(money($invoice->currency, $invoice->discount_amount)) ?></dd></div>
                <div><dt>Total</dt><dd><?= e(money($invoice->currency, $invoice->total)) ?></dd></div>
                <div><dt>Paid</dt><dd><?= e(money($invoice->currency, $invoice->paid_amount)) ?></dd></div>
                <div><dt>Balance</dt><dd><?= e(money($invoice->currency, $invoice->balance)) ?></dd></div>
            </dl>
            <?php if ($invoice->notes): ?>
                <p class="mt-3 mb-0"><?= nl2br(e($invoice->notes)) ?></p>
            <?php endif; ?>
            <p class="text-muted small mt-3 mb-0">Issued <?= e(format_date($invoice->issue_date)) ?> · Due <?= e(format_date($invoice->due_date)) ?> · <?= e($invoice->currency) ?></p>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="wf-card mb-3">
            <div class="wf-card-head"><h3>Payments</h3></div>
            <?php if ($payments === []): ?>
                <p class="text-muted">No payments recorded.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($payments as $payment): ?>
                        <li class="mb-3">
                            <strong><?= e(money($invoice->currency, $payment->amount)) ?></strong>
                            <div class="text-muted small"><?= e(format_date($payment->paid_on)) ?> · <?= e(ucfirst($payment->method)) ?><?= $payment->reference ? ' · ' . e($payment->reference) : '' ?></div>
                            <?php if ($payment->notes): ?><div class="small"><?= e($payment->notes) ?></div><?php endif; ?>
                            <?php if (can('payments.manage')): ?>
                                <form method="post" action="<?= e(base_url('payments/' . $payment->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this payment?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canPay): ?>
                <form method="post" action="<?= e(base_url('invoices/' . $invoice->id . '/payments')) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <label class="form-label" for="amount">Amount</label>
                    <input class="form-control mb-2" id="amount" name="amount" inputmode="decimal" value="<?= e($invoice->balance) ?>" required>
                    <label class="form-label" for="paid_on">Date</label>
                    <input class="form-control mb-2" id="paid_on" type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" required>
                    <label class="form-label" for="method">Method</label>
                    <select class="form-select mb-2" id="method" name="method">
                        <?php foreach (\App\Services\InvoiceService::METHODS as $method): ?>
                            <option value="<?= e($method) ?>"><?= e(ucfirst($method)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label" for="reference">Reference</label>
                    <input class="form-control mb-2" id="reference" name="reference" maxlength="80">
                    <label class="form-label" for="payment_notes">Notes</label>
                    <textarea class="form-control mb-2" id="payment_notes" name="notes" rows="2" maxlength="2000"></textarea>
                    <button class="btn btn-primary" type="submit"><?= icon('cash-coin') ?> Record payment</button>
                    <p class="text-muted small mt-2 mb-0">A payment cannot be larger than the balance.</p>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
