<?php
/** @var array<string, list<array{id:int,slug:string,name:string}>> $permissionGroups */
/** @var list<int> $selectedPermissionIds */
/** @var bool $customEnabled */
$selected = array_fill_keys(array_map('intval', $selectedPermissionIds ?? []), true);
$customEnabled = !empty($customEnabled);
$canCustomize = $canCustomize ?? true;
$compact = !empty($compact);
$headline = $headline ?? 'Access';
$subhead = $subhead ?? 'What this person can see and do';
?>
<section class="wf-card <?= $compact ? 'wf-access-card-compact' : 'mt-3' ?>" id="wf-access-panel">
    <div class="wf-card-head wf-access-head">
        <div>
            <h3><?= e($headline) ?></h3>
            <span class="text-muted small"><?= e($subhead) ?></span>
        </div>
        <input
            class="form-control form-control-sm wf-access-search"
            type="search"
            id="wf-access-search"
            placeholder="Find: clients, leads, view all…"
            autocomplete="off"
        >
    </div>
    <?php if ($canCustomize): ?>
        <input type="hidden" name="permissions_submitted" value="1">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="custom_permissions" name="custom_permissions" value="1" <?= $customEnabled ? 'checked' : '' ?>>
            <label class="form-check-label" for="custom_permissions">Customize access for this person</label>
        </div>
    <?php endif; ?>
    <div class="wf-access-grid <?= $compact ? 'is-compact' : '' ?> <?= $canCustomize && !$customEnabled ? 'is-locked' : '' ?>" id="wf-access-grid" <?= $canCustomize && !$customEnabled ? 'aria-disabled="true"' : '' ?>>
        <?php foreach ($permissionGroups as $group => $items): ?>
            <?php
            $groupKey = preg_replace('/[^a-z0-9]+/i', '-', strtolower($group)) ?: 'group';
            $groupIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
            $checkedCount = 0;
            foreach ($groupIds as $gid) {
                if (!empty($selected[$gid])) {
                    $checkedCount++;
                }
            }
            $allChecked = $checkedCount > 0 && $checkedCount === count($groupIds);
            $searchBlob = strtolower($group . ' ' . implode(' ', array_map(
                static fn (array $item): string => $item['name'] . ' ' . $item['slug'],
                $items
            )));
            ?>
            <div class="wf-access-group" data-access-group="<?= e($groupKey) ?>" data-search="<?= e($searchBlob) ?>">
                <div class="wf-group-title-row">
                    <h4 class="wf-group-title mb-0"><?= e($group) ?> <small class="text-muted"><?= $checkedCount ?>/<?= count($items) ?></small></h4>
                    <?php if (!$canCustomize || $customEnabled): ?>
                        <button class="btn btn-sm btn-outline wf-group-toggle" type="button" data-group-toggle="<?= e($groupKey) ?>" aria-pressed="<?= $allChecked ? 'true' : 'false' ?>">
                            <?= $allChecked ? 'None' : 'All' ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="wf-access-list">
                    <?php foreach ($items as $item): ?>
                        <label class="form-check" data-search="<?= e(strtolower($item['name'] . ' ' . $item['slug'] . ' ' . $group)) ?>">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="permission_ids[]"
                                value="<?= (int) $item['id'] ?>"
                                data-perm-slug="<?= e($item['slug']) ?>"
                                data-group="<?= e($groupKey) ?>"
                                <?= !empty($selected[(int) $item['id']]) ? 'checked' : '' ?>
                                <?= $canCustomize && !$customEnabled ? 'disabled' : '' ?>
                            >
                            <span>
                                <strong><?= e($item['name']) ?></strong>
                                <?php if (!$compact): ?><small><?= e($item['slug']) ?></small><?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="wf-access-empty text-muted small d-none mb-0" id="wf-access-empty">No permissions match that search.</p>
    <p class="text-muted small mt-2 mb-0 wf-access-tip">
        <strong>Sales tip:</strong> tick <em>View all leads</em> / <em>View all clients</em> for company-wide lists; leave unchecked for “only my accounts”.
    </p>
</section>
<script>
(function () {
    var grid = document.getElementById('wf-access-grid');
    var search = document.getElementById('wf-access-search');
    var empty = document.getElementById('wf-access-empty');
    if (!grid) return;

    function refreshToggle(groupKey) {
        var btn = grid.querySelector('[data-group-toggle="' + groupKey + '"]');
        if (!btn) return;
        var boxes = grid.querySelectorAll('input[data-group="' + groupKey + '"]:not(:disabled)');
        var checked = 0;
        boxes.forEach(function (box) { if (box.checked) checked++; });
        var allOn = boxes.length > 0 && checked === boxes.length;
        btn.textContent = allOn ? 'None' : 'All';
        btn.setAttribute('aria-pressed', allOn ? 'true' : 'false');
    }

    grid.querySelectorAll('[data-group-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var groupKey = btn.getAttribute('data-group-toggle');
            var boxes = grid.querySelectorAll('input[data-group="' + groupKey + '"]:not(:disabled)');
            var allOn = true;
            boxes.forEach(function (box) { if (!box.checked) allOn = false; });
            boxes.forEach(function (box) { box.checked = !allOn; });
            refreshToggle(groupKey);
        });
    });

    grid.querySelectorAll('input[data-group]').forEach(function (box) {
        box.addEventListener('change', function () {
            refreshToggle(box.getAttribute('data-group'));
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            var visibleGroups = 0;
            grid.querySelectorAll('.wf-access-group').forEach(function (group) {
                var groupHit = !q || (group.getAttribute('data-search') || '').indexOf(q) !== -1;
                var anyItem = false;
                group.querySelectorAll('.form-check').forEach(function (row) {
                    var hit = !q || (row.getAttribute('data-search') || '').indexOf(q) !== -1 || groupHit;
                    row.classList.toggle('d-none', q !== '' && !hit);
                    if (hit) anyItem = true;
                });
                var show = !q || anyItem;
                group.classList.toggle('d-none', !show);
                if (show) visibleGroups++;
            });
            if (empty) empty.classList.toggle('d-none', visibleGroups > 0);
        });
        search.focus();
    }
})();
</script>
