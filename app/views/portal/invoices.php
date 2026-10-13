<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Portal</p>
        <h2>Invoices</h2>
        <p class="text-muted mb-0">Draft invoices stay with your account team until they are sent.</p>
    </div>
</div>
<section class="wf-card">
    <?php if ($invoices === []): ?>
        <?php $title = 'No invoices yet'; $message = 'Sent invoices will appear here.'; include BASE_PATH . '/app/views/components/empty-state.php'; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead>
                    <tr><th>Number</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><a href="<?= e(base_url('portal/invoices/' . $invoice->id)) ?>"><?= e($invoice->number) ?></a></td>
                            <td><?= e(format_date($invoice->due_date)) ?></td>
                            <td><?= e(money($invoice->currency, $invoice->total)) ?></td>
                            <td><?= e(money($invoice->currency, $invoice->balance)) ?></td>
                            <td><?php \App\Core\View::component('status-badge', ['status' => $invoice->displayStatus()]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
