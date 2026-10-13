<?php
$profit = (float) $metrics['revenue'] - (float) $expenses;
$chartMax = 0.0;
foreach ($trend as $point) {
    $chartMax = max($chartMax, (float) $point['amount']);
}
$chartMax = $chartMax > 0 ? $chartMax : 1;
$bars = [
    ['label' => 'Revenue', 'amount' => (float) $metrics['revenue']],
    ['label' => 'Collected', 'amount' => (float) $metrics['collected']],
    ['label' => 'Expenses', 'amount' => (float) $expenses],
    ['label' => 'Profit', 'amount' => $profit],
    ['label' => 'Outstanding', 'amount' => (float) $metrics['outstanding']],
    ['label' => 'Overdue', 'amount' => (float) $metrics['overdue_amount']],
];
$barMax = 1.0;
foreach ($bars as $bar) {
    $barMax = max($barMax, abs($bar['amount']));
}
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Finance</p>
        <h2>Financial dashboard</h2>
        <p class="text-muted mb-0">Totals come from invoices, payments, and approved expenses. They are not a general ledger.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('invoices.view')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('invoices')) ?>"><?= icon('receipt') ?> Invoices</a>
        <?php endif; ?>
        <?php if (can('expenses.view')): ?>
            <a class="btn btn-outline" href="<?= e(base_url('expenses')) ?>"><?= icon('wallet2') ?> Expenses</a>
        <?php endif; ?>
    </div>
</div>

<section class="wf-card mb-3">
    <div class="wf-card-head">
        <h3><?= icon('graph-up') ?> Payments</h3>
        <div class="wf-finance-legend">
            <span><i class="wf-finance-swatch"></i> Monthly</span>
            <span><i class="wf-finance-swatch is-line"></i> Trend</span>
        </div>
    </div>
    <?php
    $count = max(1, count($trend));
    $width = 720;
    $height = 180;
    $padL = 8;
    $padR = 8;
    $padT = 16;
    $padB = 28;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $barW = 14;
    $slot = $plotW / $count;
    $baseY = $padT + $plotH;
    $points = [];
    $total = 0.0;
    $peak = null;
    foreach ($trend as $index => $point) {
        $amount = (float) $point['amount'];
        $total += $amount;
        if ($peak === null || $amount > $peak['amount']) {
            $peak = ['month' => $point['month'], 'amount' => $amount];
        }
        $barH = $amount > 0 ? max(3, ($amount / $chartMax) * $plotH) : 0;
        $x = $padL + ($index * $slot) + (($slot - $barW) / 2);
        $points[] = [
            'x' => $x,
            'cx' => $x + ($barW / 2),
            'y' => $baseY - $barH,
            'h' => $barH,
            'month' => $point['month'],
            'amount' => $amount,
        ];
    }
    $latest = $points === [] ? 0.0 : $points[count($points) - 1]['amount'];
    $prior = count($points) > 1 ? $points[count($points) - 2]['amount'] : 0.0;
    $change = $latest - $prior;
    $average = $count > 0 ? $total / $count : 0.0;
    $line = implode(' ', array_map(static fn (array $point): string => $point['cx'] . ',' . $point['y'], $points));
    ?>
    <div class="wf-finance-layout">
        <svg class="wf-finance-chart" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-label="Monthly payments with a trend line for the last six months">
            <?php for ($step = 0; $step <= 3; $step++): ?>
                <?php $y = $padT + ($plotH * $step / 3); ?>
                <line class="wf-finance-grid" x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>"></line>
            <?php endfor; ?>
            <?php foreach ($points as $point): ?>
                <?php if ($point['h'] > 0): ?>
                    <rect class="wf-finance-bar" x="<?= $point['x'] ?>" y="<?= $point['y'] ?>" width="<?= $barW ?>" height="<?= $point['h'] ?>" rx="3">
                        <title><?= e($point['month'] . ': ' . money($currency, $point['amount'])) ?></title>
                    </rect>
                <?php endif; ?>
                <text class="wf-finance-label" x="<?= $point['cx'] ?>" y="<?= $height - 8 ?>" text-anchor="middle"><?= e($point['month']) ?></text>
            <?php endforeach; ?>
            <?php if ($line !== ''): ?>
                <polyline class="wf-finance-line" points="<?= e($line) ?>"></polyline>
                <?php foreach ($points as $point): ?>
                    <circle class="wf-finance-dot" cx="<?= $point['cx'] ?>" cy="<?= $point['y'] ?>" r="3.5">
                        <title><?= e($point['month'] . ': ' . money($currency, $point['amount'])) ?></title>
                    </circle>
                <?php endforeach; ?>
            <?php endif; ?>
        </svg>
        <aside class="wf-finance-report">
            <h3>Six-month report</h3>
            <p>
                <?php if ($total <= 0): ?>
                    No payments were recorded in this period.
                <?php else: ?>
                    <?= e(money($currency, $total)) ?> collected.
                    <?php if ($peak !== null): ?>The strongest month was <?= e($peak['month']) ?>.<?php endif; ?>
                    <?php if (count($points) > 1): ?>
                        The latest month is <?= $change >= 0 ? 'up' : 'down' ?> <?= e(money($currency, abs($change))) ?> from the month before.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <dl>
                <div><dt>Total</dt><dd><?= e(money($currency, $total)) ?></dd></div>
                <div><dt>Average</dt><dd><?= e(money($currency, $average)) ?></dd></div>
                <div><dt>Highest</dt><dd><?= $peak === null ? '' : e($peak['month'] . ' · ' . money($currency, $peak['amount'])) ?></dd></div>
            </dl>
            <table class="table wf-table wf-keep align-middle mb-0">
                <thead>
                <tr><th>Month</th><th>Collected</th></tr>
                </thead>
                <tbody>
                <?php foreach ($points as $point): ?>
                    <tr>
                        <td><?= e($point['month']) ?></td>
                        <td><?= e(money($currency, $point['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </aside>
    </div>
</section>

<section class="wf-card">
    <div class="wf-card-head"><h3>Overview</h3></div>
    <p class="text-muted">Revenue excludes drafts and cancellations. Expenses are approved costs. Profit is revenue minus those expenses. <?= (int) $metrics['paid_count'] ?> paid invoice<?= (int) $metrics['paid_count'] === 1 ? '' : 's' ?>, <?= (int) $metrics['overdue_count'] ?> overdue.</p>
    <div class="wf-finance-bars">
        <?php foreach ($bars as $bar): ?>
            <?php $share = $barMax > 0 ? max(0, ($bar['amount'] / $barMax) * 100) : 0; ?>
            <div class="wf-finance-row">
                <span><?= e($bar['label']) ?></span>
                <div class="wf-finance-track">
                    <div class="wf-finance-fill<?= $bar['amount'] < 0 ? ' is-negative' : '' ?>" style="width: <?= e((string) round($share, 2)) ?>%"></div>
                </div>
                <strong><?= e(money($currency, $bar['amount'])) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="wf-finance-reports">
    <section class="wf-card">
        <div class="wf-card-head"><h3><?= icon('people') ?> Top clients</h3></div>
        <p class="text-muted">Ranked by sold work. Each row is the client name with invoice count, amount sold, and amount collected.</p>
        <?php if ($reports['clients'] === []): ?>
            <p class="mb-0">No invoiced clients yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table wf-keep align-middle mb-0">
                    <thead><tr><th>#</th><th>Client</th><th>Invoices</th><th>Sold</th><th>Collected</th></tr></thead>
                    <tbody>
                    <?php foreach ($reports['clients'] as $index => $row): ?>
                        <tr>
                            <td><?= (int) $index + 1 ?></td>
                            <td><?= e((string) $row['client_name']) ?></td>
                            <td><?= (int) $row['invoices'] ?></td>
                            <td><?= e(money($currency, $row['sold'])) ?></td>
                            <td><?= e(money($currency, $row['collected'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="wf-card">
        <div class="wf-card-head"><h3><?= icon('check2-circle') ?> Most done work</h3></div>
        <p class="text-muted">Projects with the most completed tasks, shown with the client name and the task count.</p>
        <?php if ($reports['done'] === []): ?>
            <p class="mb-0">No completed tasks yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table wf-keep align-middle mb-0">
                    <thead><tr><th>#</th><th>Work</th><th>Client</th><th>Done</th><th>Tasks</th></tr></thead>
                    <tbody>
                    <?php foreach ($reports['done'] as $index => $row): ?>
                        <tr>
                            <td><?= (int) $index + 1 ?></td>
                            <td><?= e((string) $row['project_name']) ?><?php if ($row['project_code']): ?><div class="text-muted small"><?= e((string) $row['project_code']) ?></div><?php endif; ?></td>
                            <td><?= e((string) $row['client_name']) ?></td>
                            <td><?= (int) $row['done_tasks'] ?></td>
                            <td><?= (int) $row['tasks'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="wf-card">
        <div class="wf-card-head"><h3><?= icon('bag') ?> Sold work</h3></div>
        <p class="text-muted">Invoice lines, with the work name, the client, the project, how many were sold, and the amount.</p>
        <?php if ($reports['sold'] === []): ?>
            <p class="mb-0">No sold work yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table wf-keep align-middle mb-0">
                    <thead><tr><th>#</th><th>Work</th><th>Client</th><th>Project</th><th>Qty</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($reports['sold'] as $index => $row): ?>
                        <tr>
                            <td><?= (int) $index + 1 ?></td>
                            <td><?= e((string) $row['work_name']) ?><div class="text-muted small"><?= (int) $row['times'] ?> line<?= (int) $row['times'] === 1 ? '' : 's' ?></div></td>
                            <td><?= e((string) $row['client_name']) ?></td>
                            <td><?= e((string) $row['project_name']) ?></td>
                            <td><?= e(rtrim(rtrim(number_format((float) $row['quantity'], 2), '0'), '.')) ?></td>
                            <td><?= e(money($currency, $row['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="wf-card">
        <div class="wf-card-head"><h3><?= icon('arrow-repeat') ?> Frequently worked jobs</h3></div>
        <p class="text-muted">Job names that appear most often, with how many times they were done and which clients and projects they belong to.</p>
        <?php if ($reports['jobs'] === []): ?>
            <p class="mb-0">No jobs yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table wf-table wf-keep align-middle mb-0">
                    <thead><tr><th>#</th><th>Job</th><th>Times</th><th>Done</th><th>Connected to</th></tr></thead>
                    <tbody>
                    <?php foreach ($reports['jobs'] as $index => $row): ?>
                        <tr>
                            <td><?= (int) $index + 1 ?></td>
                            <td><?= e((string) $row['job_name']) ?></td>
                            <td><?= (int) $row['times'] ?></td>
                            <td><?= (int) $row['done_times'] ?></td>
                            <td><?= e((string) $row['client_names']) ?><div class="text-muted small"><?= e((string) $row['project_names']) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="wf-card wf-finance-span">
        <div class="wf-card-head"><h3><?= icon('diagram-3') ?> Client and work hierarchy</h3></div>
        <p class="text-muted">Each client, then the projects under that client, with completed tasks and the amount sold.</p>
        <?php if ($reports['hierarchy'] === []): ?>
            <p class="mb-0">No client work yet.</p>
        <?php else: ?>
            <ul class="wf-finance-tree">
                <?php foreach ($reports['hierarchy'] as $client): ?>
                    <li>
                        <strong><?= e((string) $client['client_name']) ?></strong>
                        <span class="text-muted"><?= (int) $client['projects'] ?> project<?= (int) $client['projects'] === 1 ? '' : 's' ?> · <?= (int) $client['done_tasks'] ?> of <?= (int) $client['tasks'] ?> tasks done · <?= e(money($currency, $client['sold'])) ?> sold</span>
                        <ul>
                            <?php foreach ($client['items'] as $project): ?>
                                <li>
                                    <strong><?= e((string) $project['project_name']) ?></strong>
                                    <?php if ($project['project_code'] !== ''): ?><span class="text-muted"><?= e((string) $project['project_code']) ?></span><?php endif; ?>
                                    <span class="text-muted"><?= e((string) $project['status_name']) ?> · <?= (int) $project['done_tasks'] ?> of <?= (int) $project['tasks'] ?> done · <?= e(money($currency, $project['sold'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
