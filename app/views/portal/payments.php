<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Portal</p>
        <h2>Payments</h2>
        <p class="text-muted mb-0">Payments recorded against your invoices.</p>
    </div>
</div>
<section class="wf-card">
    <?php if ($payments === []): ?>
        <?php $title = 'No payments yet'; $message = 'Payments will appear here after they are recorded.'; include BASE_PATH . '/app/views/components/empty-state.php'; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead><tr><th>Date</th><th>Invoice</th><th>Method</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= e(format_date($payment->paid_on)) ?></td>
                            <td><a href="<?= e(base_url('portal/invoices/' . $payment->invoice_id)) ?>"><?= e($payment->invoice_number ?: 'Invoice') ?></a></td>
                            <td><?= e(ucfirst($payment->method)) ?></td>
                            <td><?= e(money($payment->invoice_currency ?: 'USD', $payment->amount)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
