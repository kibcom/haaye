<?php
$cal = $cal ?? 'greg';
$viewMode = $viewMode ?? 'month';
$focusDate = $focusDate ?? date('Y-m-d');
$queryBase = $cal === 'eth' ? 'cal=eth&' : '';
$focus = new DateTimeImmutable($focusDate);
$visibleWeeks = $weeks;
$dayCell = null;
if ($viewMode === 'week' || $viewMode === 'day') {
    $visibleWeeks = [];
    foreach ($weeks as $week) {
        foreach ($week as $cell) {
            if ($cell['date'] === $focusDate) {
                $visibleWeeks = [$week];
                $dayCell = $cell;
                break 2;
            }
        }
    }
    if ($visibleWeeks === []) {
        $visibleWeeks = [$weeks[0] ?? []];
        $dayCell = $visibleWeeks[0][0] ?? null;
    }
}
$chipLimit = $viewMode === 'week' ? 4 : 2;
$navPrev = $viewMode === 'day'
    ? $focus->modify('-1 day')
    : ($viewMode === 'week' ? $focus->modify('-7 days') : null);
$navNext = $viewMode === 'day'
    ? $focus->modify('+1 day')
    : ($viewMode === 'week' ? $focus->modify('+7 days') : null);
$link = static function (string $view, string $month, string $day = '') use ($queryBase): string {
    $url = 'calendar?' . $queryBase . 'view=' . $view . '&month=' . $month;
    if ($day !== '') {
        $url .= '&day=' . $day;
    }

    return $url;
};
$renderTask = static function (array $item): void {
    $task = $item['task'];
    $canOpen = !empty($item['can_open']);
    $statusLabel = $task->status_name ?: ucwords(str_replace('_', ' ', (string) $task->status));
    $tone = $task->status_color ?: '#64748B';
    $meta = trim(($task->assignee_name ?: 'Unassigned') . ' · ' . $statusLabel);
    $class = 'wf-cal-task' . ($canOpen ? '' : ' is-locked');
    $open = $canOpen ? 'a' : 'div';
    $href = $canOpen ? ' href="' . e(base_url('tasks/' . $task->id)) . '"' : '';
    echo '<' . $open . ' class="' . $class . '"' . $href . ' style="--cal-tone: ' . e($tone) . '" title="' . e($task->title) . '">';
    echo '<strong>' . e($task->title) . '</strong>';
    echo '<span>' . e($meta) . '</span>';
    echo '</' . $open . '>';
};
?>
<div class="wf-cal-page">
    <div class="wf-cal-hero">
        <div class="wf-cal-hero-copy">
            <span class="wf-cal-hero-mark" aria-hidden="true"><?= icon('calendar3') ?></span>
            <div>
                <h2>Calendar</h2>
                <p>Stay organized. Meet deadlines. Get things done.</p>
            </div>
        </div>
        <div class="wf-cal-hero-actions">
            <div class="wf-cal-switch btn-group">
                <a class="btn btn-sm <?= $cal === 'greg' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url('calendar?view=' . $viewMode . '&month=' . ($cal === 'greg' ? $monthKey : date('Y-m')) . ($viewMode !== 'month' ? '&day=' . $focusDate : ''))) ?>">Gregorian</a>
                <a class="btn btn-sm <?= $cal === 'eth' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url('calendar?cal=eth&view=' . $viewMode . ($cal === 'eth' ? '&month=' . $monthKey : '') . ($viewMode !== 'month' ? '&day=' . $focusDate : ''))) ?>">Ethiopian</a>
            </div>
            <?php if (can('tasks.create')): ?>
                <a class="btn btn-aqua" href="<?= e(base_url('tasks/create')) ?>"><?= icon('plus-lg') ?> New task</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="wf-cal-layout">
        <section class="wf-cal-board" aria-label="Task calendar for <?= e($monthLabel) ?>">
            <div class="wf-cal-board-bar">
                <div class="wf-cal-views btn-group">
                    <a class="btn btn-sm <?= $viewMode === 'month' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url('calendar?' . $queryBase . 'month=' . $monthKey)) ?>">Month</a>
                    <a class="btn btn-sm <?= $viewMode === 'week' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url($link('week', $focus->format('Y-m'), $focusDate))) ?>">Week</a>
                    <a class="btn btn-sm <?= $viewMode === 'day' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url($link('day', $focus->format('Y-m'), $focusDate))) ?>">Day</a>
                </div>
                <div class="wf-cal-pager">
                    <?php if ($viewMode === 'month'): ?>
                        <a class="wf-cal-arrow" href="<?= e(base_url('calendar?' . $queryBase . 'month=' . $previousMonth)) ?>" aria-label="Previous month"><?= icon('chevron-left') ?></a>
                        <strong><?= e($monthLabel) ?></strong>
                        <a class="wf-cal-arrow" href="<?= e(base_url('calendar?' . $queryBase . 'month=' . $nextMonth)) ?>" aria-label="Next month"><?= icon('chevron-right') ?></a>
                    <?php else: ?>
                        <a class="wf-cal-arrow" href="<?= e(base_url($link($viewMode, $navPrev->format('Y-m'), $navPrev->format('Y-m-d')))) ?>" aria-label="Previous"><?= icon('chevron-left') ?></a>
                        <strong><?= e($viewMode === 'day' ? $focus->format('l, M j') : $monthLabel) ?></strong>
                        <a class="wf-cal-arrow" href="<?= e(base_url($link($viewMode, $navNext->format('Y-m'), $navNext->format('Y-m-d')))) ?>" aria-label="Next"><?= icon('chevron-right') ?></a>
                    <?php endif; ?>
                </div>
                <form class="wf-filters wf-cal-filters" method="get" action="<?= e(base_url('calendar')) ?>">
                    <?php if ($cal === 'eth'): ?>
                        <input type="hidden" name="cal" value="eth">
                    <?php endif; ?>
                    <input type="hidden" name="view" value="<?= e($viewMode) ?>">
                    <label class="visually-hidden" for="cal-year">Year</label>
                    <select class="form-select form-select-sm" id="cal-year" name="year" aria-label="Year">
                        <?php foreach ($yearOptions as $yearValue => $yearLabel): ?>
                            <option value="<?= (int) $yearValue ?>" <?= selected($filterYear ?? '', $yearValue) ?>><?= e((string) $yearLabel) ?><?= $cal === 'eth' ? ' E.C.' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="visually-hidden" for="cal-month">Month</label>
                    <select class="form-select form-select-sm" id="cal-month" name="m" aria-label="Month">
                        <?php foreach ($monthOptions as $monthValue => $monthName): ?>
                            <option value="<?= (int) $monthValue ?>" <?= selected($filterMonth ?? '', $monthValue) ?>><?= e($monthName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($viewMode === 'day'): ?>
                <div class="wf-cal-dayview">
                    <?php $dayTasks = $dayCell['tasks'] ?? []; ?>
                    <?php if ($dayTasks === []): ?>
                        <p class="wf-cal-empty">Nothing due this day.</p>
                    <?php else: ?>
                        <?php foreach ($dayTasks as $item): ?>
                            <?php $renderTask($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="wf-calendar">
                    <div class="wf-cal-head">
                        <?php foreach ($weekdayLabels as $label): ?>
                            <div><?= e($label) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="wf-cal-grid">
                        <?php foreach ($visibleWeeks as $week): ?>
                            <?php foreach ($week as $cell): ?>
                                <?php $count = count($cell['tasks']); ?>
                                <div class="wf-cal-day <?= $cell['in_month'] ? '' : 'is-outside' ?> <?= $cell['is_today'] ? 'is-today' : '' ?> <?= $viewMode === 'week' && $cell['date'] === $focusDate ? 'is-focus' : '' ?>">
                                    <a class="wf-cal-daynum" href="<?= e(base_url($link('day', substr($cell['date'], 0, 7), $cell['date']))) ?>">
                                        <strong><?= (int) $cell['day'] ?></strong>
                                        <?php if ($count > 0): ?>
                                            <small><?= (int) $count ?> <?= $count === 1 ? 'Task' : 'Tasks' ?></small>
                                        <?php elseif ($cal === 'eth' && !empty($cell['sub'])): ?>
                                            <small><?= e($cell['sub']) ?></small>
                                        <?php endif; ?>
                                    </a>
                                    <div class="wf-cal-items">
                                        <?php foreach (array_slice($cell['tasks'], 0, $chipLimit) as $item): ?>
                                            <?php $renderTask($item); ?>
                                        <?php endforeach; ?>
                                        <?php if ($count > $chipLimit): ?>
                                            <a class="wf-cal-more" href="<?= e(base_url($link('day', substr($cell['date'], 0, 7), $cell['date']))) ?>">+<?= (int) ($count - $chipLimit) ?> more</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <aside class="wf-cal-rail">
            <section class="wf-cal-card">
                <h3><?= icon('calendar2-check') ?> Today's Agenda</h3>
                <?php if (($todayItems ?? []) === []): ?>
                    <p class="wf-cal-empty">Nothing due today.</p>
                <?php else: ?>
                    <ul class="wf-cal-agenda">
                        <?php foreach ($todayItems as $item): ?>
                            <?php $task = $item['task']; ?>
                            <li>
                                <?php if (!empty($item['can_open'])): ?>
                                    <a href="<?= e(base_url('tasks/' . $task->id)) ?>">
                                        <strong><?= e($task->title) ?></strong>
                                        <span><?= e($task->assignee_name ?: 'Unassigned') ?></span>
                                    </a>
                                <?php else: ?>
                                    <div>
                                        <strong><?= e($task->title) ?></strong>
                                        <span><?= e($task->assignee_name ?: 'Unassigned') ?></span>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="wf-cal-card">
                <div class="wf-cal-card-head">
                    <h3><?= icon('calendar2-week') ?> Upcoming</h3>
                    <a href="<?= e(base_url('tasks')) ?>">View all</a>
                </div>
                <?php if (($upcoming ?? []) === []): ?>
                    <p class="wf-cal-empty">No upcoming tasks.</p>
                <?php else: ?>
                    <ul class="wf-cal-upcoming">
                        <?php foreach ($upcoming as $item): ?>
                            <?php
                            $task = $item['task'];
                            $when = new DateTimeImmutable($item['due']);
                            ?>
                            <li>
                                <span class="wf-cal-when"><strong><?= e($when->format('M')) ?></strong><?= e($when->format('j')) ?></span>
                                <?php if (!empty($item['can_open'])): ?>
                                    <a href="<?= e(base_url('tasks/' . $task->id)) ?>">
                                        <strong><?= e($task->title) ?></strong>
                                        <small><?= e($task->assignee_name ?: 'Unassigned') ?> · <?= e($task->status_name ?: ucwords(str_replace('_', ' ', (string) $task->status))) ?></small>
                                    </a>
                                <?php else: ?>
                                    <div>
                                        <strong><?= e($task->title) ?></strong>
                                        <small><?= e($task->assignee_name ?: 'Unassigned') ?></small>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <p class="wf-cal-note">Small steps every day lead to big results.</p>
        </aside>
    </div>
</div>
