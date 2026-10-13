<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2>Expenses</h2>
        <p class="text-muted mb-0">Only approved expenses count toward project profit.</p>
    </div>
    <?php if (can('expenses.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('expenses/create')) ?>"><?= icon('plus-lg') ?> Record expense</a>
    <?php endif; ?>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label): ?>
        <a class="btn btn-sm <?= $status === $value ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url('expenses' . ($value !== '' ? '?status=' . $value : ''))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<section class="wf-card">
    <?php if ($expenses === []): ?>
        <?php
        $title = 'No expenses';
        $message = 'Expenses you can see will appear here.';
        include BASE_PATH . '/app/views/components/empty-state.php';
        ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Employee</th>
                        <th>Project</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><a href="<?= e(base_url('expenses/' . $expense->id)) ?>"><?= e(format_date($expense->spent_on)) ?></a></td>
                            <td><?= e(ucfirst($expense->category)) ?></td>
                            <td><?= e($expense->user_name ?: 'Former user') ?></td>
                            <td><?= e($expense->project_name ?: '') ?></td>
                            <td><?= e(money($currency, $expense->amount)) ?></td>
                            <td><?php \App\Core\View::component('status-badge', ['status' => $expense->status]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
