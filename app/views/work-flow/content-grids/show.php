<?php
/** @var \App\Models\ContentGrid $grid */
/** @var array<int, list<\App\Models\ContentGridDay>> $weeks */
/** @var list<array{key:string,label:string,icon:string}> $platforms */
/** @var list<string> $postTypes */
$canManage = !empty($canManage);
$disabled = $canManage ? '' : ' disabled';
?>
<div class="cg-sheet">
    <div class="cg-sheet-top">
        <div class="cg-brand-block">
            <div class="cg-logo-mark"><?= icon('grid-3x3-gap') ?></div>
            <div>
                <p class="cg-kicker mb-0">Monthly Content Grid</p>
                <h1 class="cg-company"><?= e($grid->client_name ?: 'Company') ?></h1>
            </div>
        </div>
        <div class="cg-meta">
            <div class="cg-meta-row">
                <span>Start Date</span>
                <strong><?= e(format_date($grid->start_date)) ?></strong>
            </div>
            <div class="cg-meta-row">
                <span>End Date</span>
                <strong><?= e(format_date($grid->end_date)) ?></strong>
            </div>
            <div class="d-flex gap-2 mt-2 justify-content-end flex-wrap">
                <a class="btn btn-sm btn-outline" href="<?= e(base_url('work-flow/content-grids')) ?>"><?= icon('arrow-left') ?> All grids</a>
            </div>
        </div>
    </div>

    <form method="post" action="<?= e(base_url('work-flow/content-grids/' . $grid->id)) ?>">
        <?= csrf_field() ?>
        <div class="cg-toolbar row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label" for="cg_start">Start date</label>
                <input class="form-control" id="cg_start" type="date" name="start_date" value="<?= e($grid->start_date) ?>" required<?= $disabled ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="cg_end">End date</label>
                <input class="form-control" id="cg_end" type="date" name="end_date" value="<?= e($grid->end_date) ?>" required<?= $disabled ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="cg_notes">Notes</label>
                <input class="form-control" id="cg_notes" name="notes" maxlength="255" value="<?= e((string) $grid->notes) ?>"<?= $disabled ?>>
            </div>
            <?php if ($canManage): ?>
            <div class="col-md-2">
                <button class="btn btn-aqua w-100" type="submit"><?= icon('check-lg') ?> Save grid</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive cg-table-wrap">
            <table class="table cg-table mb-0">
                <thead>
                    <tr>
                        <th class="cg-col-day">Day</th>
                        <th class="cg-col-type">Post type</th>
                        <th class="cg-col-time">Time</th>
                        <th class="cg-col-platforms" colspan="<?= count($platforms) ?>">Platforms</th>
                        <th class="cg-col-status" colspan="2">Status</th>
                    </tr>
                    <tr class="cg-subhead">
                        <th></th>
                        <th></th>
                        <th></th>
                        <?php foreach ($platforms as $platform): ?>
                            <th class="cg-platform-head" title="<?= e($platform['label']) ?>">
                                <span class="cg-platform-icon"><?= icon($platform['icon']) ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="cg-status-head">Edited</th>
                        <th class="cg-status-head">Posted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeks as $weekNumber => $days): ?>
                        <tr class="cg-week-row">
                            <td colspan="<?= 5 + count($platforms) ?>">
                                <span class="cg-week-ribbon">Week <?= (int) $weekNumber ?></span>
                            </td>
                        </tr>
                        <?php foreach ($days as $day): ?>
                            <?php
                            $prefix = 'days[' . (int) $day->id . ']';
                            $isRest = strcasecmp($day->post_type, 'No Post') === 0;
                            ?>
                            <tr class="<?= $isRest ? 'cg-rest-row' : '' ?>">
                                <td class="cg-day-name"><?= e(\App\Services\ContentGridService::dayName((int) $day->day_of_week)) ?></td>
                                <td>
                                    <input
                                        class="form-control form-control-sm"
                                        list="cg-post-types"
                                        name="<?= e($prefix) ?>[post_type]"
                                        value="<?= e($day->post_type) ?>"
                                        maxlength="80"
                                        placeholder="e.g. Reel"
                                        required<?= $disabled ?>
                                    >
                                </td>
                                <td>
                                    <input class="form-control form-control-sm" name="<?= e($prefix) ?>[post_time]" value="<?= e($day->post_time) ?>" maxlength="40"<?= $disabled ?>>
                                </td>
                                <?php foreach ($platforms as $platform): ?>
                                    <?php $col = 'platform_' . $platform['key']; ?>
                                    <td class="text-center">
                                        <?php if ($canManage): ?>
                                            <input type="hidden" name="<?= e($prefix) ?>[<?= e($col) ?>]" value="0">
                                        <?php endif; ?>
                                        <input class="form-check-input" type="checkbox" name="<?= e($prefix) ?>[<?= e($col) ?>]" value="1" <?= checked((int) $day->{$col}, 1) ?> title="<?= e($platform['label']) ?>"<?= $disabled ?>>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-center">
                                    <?php if ($canManage): ?>
                                        <input type="hidden" name="<?= e($prefix) ?>[is_edited]" value="0">
                                    <?php endif; ?>
                                    <input class="form-check-input" type="checkbox" name="<?= e($prefix) ?>[is_edited]" value="1" <?= checked((int) $day->is_edited, 1) ?><?= $disabled ?>>
                                </td>
                                <td class="text-center">
                                    <?php if ($canManage): ?>
                                        <input type="hidden" name="<?= e($prefix) ?>[is_posted]" value="0">
                                    <?php endif; ?>
                                    <input class="form-check-input" type="checkbox" name="<?= e($prefix) ?>[is_posted]" value="1" <?= checked((int) $day->is_posted, 1) ?><?= $disabled ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <datalist id="cg-post-types">
            <?php foreach ($postTypes as $type): ?>
                <option value="<?= e($type) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
            <p class="text-muted small mb-0">
                <?php if ($canManage): ?>
                    Post type: pick a suggestion or type a new one (e.g. Live, Podcast). Then mark platforms, Edited, and Posted.
                <?php else: ?>
                    View-only — ask a Social Media Manager or Operations lead to update this grid.
                <?php endif; ?>
            </p>
            <?php if ($canManage): ?>
                <button class="btn btn-aqua" type="submit"><?= icon('check-lg') ?> Save grid</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
/* Force a light printable sheet so dark theme tokens cannot wash out text. */
.cg-sheet {
    --cg-ink: #1f2937;
    --cg-muted: #64748b;
    --cg-line: #e2e8f0;
    --cg-paper: #ffffff;
    --cg-cream: #fff7ed;
    --cg-orange: #ea580c;
    --cg-orange-soft: #ffedd5;
    color: var(--cg-ink);
    background: var(--cg-paper);
    border: 1px solid var(--cg-line);
    border-radius: 16px;
    padding: 20px 18px 24px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}
.cg-sheet-top {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 2px solid var(--cg-orange);
}
.cg-brand-block { display: flex; gap: 12px; align-items: center; }
.cg-logo-mark {
    width: 48px; height: 48px; border-radius: 12px;
    display: grid; place-items: center;
    background: var(--cg-orange);
    color: #fff; font-size: 1.25rem;
}
.cg-kicker {
    text-transform: uppercase;
    letter-spacing: .08em;
    font-size: 11px;
    font-weight: 700;
    color: var(--cg-orange);
}
.cg-company {
    margin: 0;
    font-size: clamp(1.4rem, 2vw, 1.9rem);
    font-weight: 800;
    color: var(--cg-ink);
}
.cg-meta { text-align: right; min-width: 180px; }
.cg-meta-row {
    display: flex; justify-content: flex-end; gap: 10px;
    font-size: 13px; color: var(--cg-muted);
}
.cg-meta-row strong { color: var(--cg-ink); }
.cg-toolbar .form-label { color: var(--cg-muted); font-weight: 600; }
.cg-toolbar .form-control {
    background: #fff !important;
    color: var(--cg-ink) !important;
    border-color: #cbd5e1 !important;
}
.cg-table-wrap {
    border: 1px solid var(--cg-line);
    border-radius: 12px;
    overflow: auto;
    background: #fff;
}
.cg-table {
    --bs-table-bg: #fff;
    --bs-table-color: var(--cg-ink);
    --bs-table-border-color: var(--cg-line);
    --bs-table-hover-bg: var(--cg-cream);
    --bs-table-hover-color: var(--cg-ink);
    --bs-table-striped-bg: #fff;
    --bs-table-striped-color: var(--cg-ink);
    font-size: 13px;
    color: var(--cg-ink) !important;
    background: #fff !important;
}
.cg-table > :not(caption) > * > * {
    background-color: #fff !important;
    color: var(--cg-ink) !important;
    border-bottom-color: var(--cg-line) !important;
    box-shadow: none !important;
}
.cg-table thead th {
    background: #0f172a !important;
    color: #f8fafc !important;
    border-bottom-color: #0f172a !important;
    white-space: nowrap;
    vertical-align: middle;
    font-weight: 700;
}
.cg-subhead th {
    background: #1e293b !important;
    color: #fdba74 !important;
    font-size: 11px;
}
.cg-platform-head, .cg-status-head { text-align: center; width: 42px; }
.cg-platform-icon { color: #fdba74; font-size: 14px; }
.cg-col-day { min-width: 110px; }
.cg-col-type { min-width: 130px; }
.cg-col-time { min-width: 110px; }
.cg-week-row td {
    background: var(--cg-orange) !important;
    color: #fff !important;
    padding: 8px 12px !important;
    border-top: 0 !important;
    border-bottom: 0 !important;
}
.cg-week-ribbon {
    display: inline-block;
    color: #fff !important;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.cg-day-name {
    font-weight: 700;
    color: var(--cg-ink) !important;
}
.cg-table tbody tr:not(.cg-week-row):hover > * {
    background: var(--cg-cream) !important;
    color: var(--cg-ink) !important;
}
.cg-rest-row > * { background: #f8fafc !important; }
.cg-rest-row .cg-day-name { color: #64748b !important; }
.cg-table .form-control-sm {
    min-width: 100px;
    background: #fff !important;
    color: var(--cg-ink) !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: none !important;
}
.cg-table .form-control-sm:focus {
    border-color: var(--cg-orange) !important;
    box-shadow: 0 0 0 .2rem rgba(234, 88, 12, .15) !important;
}
.cg-table .form-check-input {
    cursor: pointer;
    width: 1.05rem;
    height: 1.05rem;
    margin: 0;
    background-color: #fff !important;
    border: 1.5px solid #94a3b8 !important;
}
.cg-table .form-check-input:checked {
    background-color: var(--cg-orange) !important;
    border-color: var(--cg-orange) !important;
}
.cg-sheet .text-muted { color: var(--cg-muted) !important; }
@media (max-width: 768px) {
    .cg-sheet { padding: 14px 10px; }
    .cg-meta { text-align: left; }
    .cg-meta-row { justify-content: flex-start; }
}
</style>
