<?php
$isEdit = $invoice instanceof \App\Models\Invoice;
$selectedClient = (int) old('client_id', $isEdit ? $invoice->client_id : 0);
$selectedProject = (int) old('project_id', $isEdit ? (int) $invoice->project_id : (int) ($prefillProject ?? 0));
$issueDate = (string) old('issue_date', $isEdit ? $invoice->issue_date : date('Y-m-d'));
$dueDate = (string) old('due_date', $isEdit ? $invoice->due_date : date('Y-m-d', strtotime('+14 days')));
$taxRate = (string) old('tax_rate', $isEdit ? $invoice->tax_rate : ($defaultTaxRate ?? '0.00'));
$discount = (string) old('discount_amount', $isEdit ? $invoice->discount_amount : '0.00');
$oldDescriptions = old('descriptions', null);
$lines = [];
if (is_array($oldDescriptions)) {
    $oldQty = old('quantities', []);
    $oldPrice = old('prices', []);
    foreach ($oldDescriptions as $index => $description) {
        $lines[] = [
            'description' => (string) $description,
            'quantity' => (string) ($oldQty[$index] ?? '1'),
            'unit_price' => (string) ($oldPrice[$index] ?? ''),
        ];
    }
} elseif ($items) {
    foreach ($items as $item) {
        $lines[] = [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ];
    }
}
if ($lines === []) {
    $lines[] = ['description' => '', 'quantity' => '1', 'unit_price' => ''];
}
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2><?= $isEdit ? 'Edit ' . e($invoice->number) : 'New invoice' ?></h2>
        <p class="text-muted mb-0">The invoice number is assigned when the draft is created. Currency follows the organization.</p>
    </div>
</div>

<section class="wf-card">
    <form method="post" action="<?= e(base_url($isEdit ? 'invoices/' . $invoice->id : 'invoices')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="client_id">Client</label>
                <select class="form-select" id="invoice-client" name="client_id" required>
                    <option value="">Select a client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client->id ?>" <?= (int) $client->id === $selectedClient ? 'selected' : '' ?>><?= e($client->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="project_id">Project</label>
                <select class="form-select" id="invoice-project" name="project_id">
                    <option value="">No project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project->id ?>" data-client="<?= (int) $project->client_id ?>" <?= (int) $project->id === $selectedProject ? 'selected' : '' ?>><?= e($project->code . ' · ' . $project->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="issue_date">Issue date</label>
                <input class="form-control" id="issue_date" type="date" name="issue_date" value="<?= e($issueDate) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="due_date">Due date</label>
                <input class="form-control" id="due_date" type="date" name="due_date" value="<?= e($dueDate) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="tax_rate">Tax %</label>
                <input class="form-control" id="tax_rate" name="tax_rate" inputmode="decimal" value="<?= e($taxRate) ?>">
                <div class="form-text">Saved for the next invoice.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="discount_amount">Discount</label>
                <input class="form-control" id="discount_amount" name="discount_amount" inputmode="decimal" value="<?= e($discount) ?>">
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table align-middle" id="invoice-lines">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="width: 120px">Qty</th>
                        <th style="width: 160px">Price</th>
                        <th style="width: 48px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr class="invoice-line">
                            <td><input class="form-control" name="descriptions[]" value="<?= e($line['description']) ?>" maxlength="255" required></td>
                            <td><input class="form-control" name="quantities[]" value="<?= e($line['quantity']) ?>" inputmode="decimal" required></td>
                            <td><input class="form-control" name="prices[]" value="<?= e($line['unit_price']) ?>" inputmode="decimal" required></td>
                            <td><button class="btn btn-sm btn-soft-danger invoice-remove" type="button"><?= icon('trash3') ?> Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn btn-outline btn-sm" id="invoice-add-line" type="button"><?= icon('plus-lg') ?> Add line</button>

        <section class="wf-invoice-payments mt-4" id="invoice-payments" data-currency="<?= e($currency ?? 'USD') ?>">
            <div class="wf-card-head"><h3>Payments</h3></div>
            <p class="text-muted mb-0" data-payment-empty>Choose a project to see what has been paid and what is still left.</p>
            <div data-payment-body hidden>
                <dl class="wf-dl">
                    <div><dt>Total payment</dt><dd data-payment-total></dd></div>
                    <div><dt>Remaining payment</dt><dd data-payment-remaining></dd></div>
                </dl>
                <h4 class="h6 mt-3">Paid history</h4>
                <ul class="list-unstyled mb-0" data-payment-history></ul>
            </div>
        </section>
        <script type="application/json" id="invoice-payment-data"><?= json_encode($paymentSnapshot ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

        <div class="mt-3">
            <label class="form-label" for="notes">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000"><?= e((string) old('notes', $isEdit ? ($invoice->notes ?? '') : '')) ?></textarea>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($isEdit ? 'check-lg' : 'plus-lg') ?> <?= $isEdit ? 'Save draft' : 'Create draft' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url($isEdit ? 'invoices/' . $invoice->id : 'invoices')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
