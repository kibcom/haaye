<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2>Invoices</h2>
        <p class="text-muted mb-0">An overdue badge means the due date has passed and a balance remains.</p>
    </div>
    <?php if (can('invoices.manage')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('invoices/create')) ?>"><?= icon('plus-lg') ?> New invoice</a>
    <?php endif; ?>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'partial' => 'Partially paid', 'overdue' => 'Overdue', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label): ?>
        <a class="btn btn-sm <?= $status === $value ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url('invoices' . ($value !== '' ? '?status=' . $value : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<section class="wf-card">
    <?php if ($invoices === []): ?>
        <?php
        $title = 'No invoices';
        $message = 'Invoices you can see will appear here.';
        $actionUrl = can('invoices.manage') ? 'invoices/create' : '';
        $actionLabel = can('invoices.manage') ? 'New invoice' : '';
        include BASE_PATH . '/app/views/components/empty-state.php';
        ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Client</th>
                        <th>Due</th>
                        <th>Total</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><a href="<?= e(base_url('invoices/' . $invoice->id)) ?>"><?= e($invoice->number) ?></a></td>
                            <td>
                                <?= e($invoice->client_name ?: 'Client') ?>
                                <?php if ($invoice->project_name): ?>
                                    <div class="text-muted small"><?= e($invoice->project_code) ?> · <?= e($invoice->project_name) ?></div>
                                <?php endif; ?>
                            </td>
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
