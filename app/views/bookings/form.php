<?php
$editing = $booking !== null;
$labels = ['scheduled' => 'Scheduled', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$startValue = old('starts_at', $editing ? date('Y-m-d\TH:i', strtotime((string) $booking->starts_at)) : '');
$endValue = old('ends_at', $editing ? date('Y-m-d\TH:i', strtotime((string) $booking->ends_at)) : '');
$chosenProject = (string) old('project_id', $projectId ?? '');
$checklist = $checklist ?? \App\Services\BookingChecklist::defaults();
$selectedEquipment = $selectedEquipment ?? ['owned_ids' => [], 'rentals' => [['item_id' => null, 'rent_from' => '', 'equipments' => '']]];
$ownedItems = $ownedItems ?? [];
$rentalItems = $rentalItems ?? [];
$roles = $workflowRoles ?? [];
$people = $people ?? [];
$approvers = $approvers ?? [];
$projectClientMap = [];
foreach ($projects as $project) {
    $projectClientMap[(int) $project->id] = (string) ($project->client_name ?? '');
}
$defaultCompany = '';
if ($chosenProject !== '' && isset($projectClientMap[(int) $chosenProject])) {
    $defaultCompany = $projectClientMap[(int) $chosenProject];
} elseif ($editing) {
    $defaultCompany = (string) ($booking->client_name ?? $booking->checklist_company ?? '');
}
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Projects</p>
        <h2><?= $editing ? 'Edit booking' : 'New booking' ?></h2>
        <p class="text-muted mb-0">Compact booking details + Quick-Start Checklist with inventory. Send to CEO or Production Manager for approval.</p>
    </div>
</div>

<form method="post" action="<?= e(base_url($editing ? 'bookings/' . $booking->id : 'bookings')) ?>" id="booking-form">
    <?= csrf_field() ?>

    <section class="wf-card mb-2 bk-compact">
        <div class="wf-card-head"><h3>Booking</h3></div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label" for="project_id">Project</label>
                <select class="form-select form-select-sm" id="project_id" name="project_id" required>
                    <option value="">Select project</option>
                    <?php foreach ($projects as $project): ?>
                        <option
                            value="<?= (int) $project->id ?>"
                            data-client="<?= e((string) ($project->client_name ?? '')) ?>"
                            <?= selected($chosenProject, $project->id) ?>
                        ><?= e($project->name) ?><?= !empty($project->client_name) ? ' · ' . e($project->client_name) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="title">Title</label>
                <input class="form-control form-control-sm" id="title" name="title" value="<?= e((string) old('title', $booking->title ?? '')) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="starts_at">Starts</label>
                <input class="form-control form-control-sm" id="starts_at" type="datetime-local" name="starts_at" value="<?= e((string) $startValue) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="ends_at">Ends</label>
                <input class="form-control form-control-sm" id="ends_at" type="datetime-local" name="ends_at" value="<?= e((string) $endValue) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="contact_name">Contact</label>
                <input class="form-control form-control-sm" id="contact_name" name="contact_name" value="<?= e((string) old('contact_name', $booking->contact_name ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="location">Location</label>
                <input class="form-control form-control-sm" id="location" name="location" value="<?= e((string) old('location', $booking->location ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <?php foreach ($labels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected(old('status', $booking->status ?? 'scheduled'), $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="notes">Notes</label>
                <input class="form-control form-control-sm" id="notes" name="notes" value="<?= e((string) old('notes', $booking->notes ?? '')) ?>">
            </div>
        </div>
    </section>

    <section class="wf-card mb-2 qs-sheet">
        <div class="wf-card-head d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div>
                <p class="qs-kicker mb-0">Production</p>
                <h3 class="mb-0">Quick-Start Checklist</h3>
            </div>
            <button class="btn btn-sm btn-outline" type="button" data-bs-toggle="modal" data-bs-target="#inv-add-modal"><?= icon('plus-lg') ?> Add inventory item</button>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-md-8">
                <label class="form-label" for="checklist_company">Company name (client)</label>
                <input class="form-control form-control-sm" id="checklist_company" name="checklist_company" value="<?= e((string) old('checklist_company', $defaultCompany)) ?>" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="checklist_date">Date</label>
                <input class="form-control form-control-sm" id="checklist_date" type="date" name="checklist_date" value="<?= e((string) old('checklist_date', $checklist['date'] ?? '')) ?>">
            </div>
        </div>

        <h4 class="qs-section-title">Owned equipment</h4>
        <div class="wf-search-select" data-search-select data-multi="1" data-name="owned_item_ids[]">
            <input class="form-control form-control-sm wf-search-input" type="search" placeholder="Search owned inventory…" autocomplete="off">
            <div class="wf-search-panel" hidden></div>
            <div class="wf-search-chips" data-chips></div>
            <select class="d-none" multiple data-source>
                <?php foreach ($ownedItems as $item): ?>
                    <option value="<?= (int) $item->id ?>" <?= in_array((int) $item->id, $selectedEquipment['owned_ids'] ?? [], true) ? 'selected' : '' ?>><?= e($item->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <h4 class="qs-section-title mt-3">Rentals</h4>
        <div id="rental-rows">
            <?php foreach (($selectedEquipment['rentals'] ?? [['item_id' => null, 'rent_from' => '', 'equipments' => '']]) as $index => $rental): ?>
                <div class="row g-2 align-items-end mb-2 rental-row">
                    <div class="col-md-5">
                        <label class="form-label">Equipment (rental inventory)</label>
                        <div class="wf-search-select" data-search-select data-name="rentals[<?= (int) $index ?>][item_id]">
                            <input class="form-control form-control-sm wf-search-input" type="search" placeholder="Search rental items…" autocomplete="off">
                            <div class="wf-search-panel" hidden></div>
                            <select class="d-none" data-source>
                                <option value="">Select rental item</option>
                                <?php foreach ($rentalItems as $item): ?>
                                    <option
                                        value="<?= (int) $item->id ?>"
                                        data-vendor="<?= e((string) $item->vendor_name) ?>"
                                        <?= selected((int) ($rental['item_id'] ?? 0), (int) $item->id) ?>
                                    ><?= e($item->name) ?><?= $item->vendor_name ? ' · ' . e($item->vendor_name) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rent from</label>
                        <input class="form-control form-control-sm rental-from" name="rentals[<?= (int) $index ?>][rent_from]" value="<?= e((string) ($rental['rent_from'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Notes / qty</label>
                        <input class="form-control form-control-sm" name="rentals[<?= (int) $index ?>][equipments]" value="<?= e((string) ($rental['equipments'] ?? '')) ?>" placeholder="e.g. 1 Soft box">
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-soft-danger w-100" type="button" data-remove-rental><?= icon('trash3') ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-outline" type="button" id="add-rental-row"><?= icon('plus-lg') ?> Add rental row</button>

        <div class="row g-2 mt-3">
            <div class="col-md-6">
                <label class="form-label" for="represented_name">Represented name</label>
                <input class="form-control form-control-sm" id="represented_name" name="represented_name" value="<?= e((string) old('represented_name', $checklist['represented_name'] ?? '')) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <label class="qs-check mb-2">
                    <input type="hidden" name="represented_signed" value="0">
                    <input class="form-check-input" type="checkbox" name="represented_signed" value="1" <?= !empty($checklist['represented_signed']) ? 'checked' : '' ?>>
                    <span>Signed</span>
                </label>
            </div>
        </div>
    </section>

    <section class="wf-card mb-2">
        <div class="wf-card-head"><h3>Submit &amp; linked task</h3></div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label" for="submit_to_user_id">Send to CEO / Production Manager</label>
                <select class="form-select form-select-sm" id="submit_to_user_id" name="submit_to_user_id">
                    <option value="">Save as draft</option>
                    <?php foreach ($approvers as $person): ?>
                        <option value="<?= (int) $person->id ?>"><?= e($person->name) ?> · <?= e($person->role_name ?: $person->role_slug) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <label class="qs-check mb-2">
                    <input type="hidden" name="submit_now" value="0">
                    <input class="form-check-input" type="checkbox" name="submit_now" value="1" id="submit_now">
                    <span>Submit for approval now</span>
                </label>
            </div>
            <?php if (!$editing || empty($booking->task_id)): ?>
                <div class="col-md-5 d-flex align-items-end">
                    <label class="qs-check mb-2">
                        <input type="hidden" name="create_linked_task" value="0">
                        <input class="form-check-input" type="checkbox" name="create_linked_task" value="1" checked>
                        <span>Create shared Workflow/Operations task</span>
                    </label>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="work_flow_role_id">Workflow role</label>
                    <select class="form-select form-select-sm" id="work_flow_role_id" name="work_flow_role_id">
                        <option value="">Any role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role->id ?>"><?= e($role->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="assignee_user_id">Assign task to</label>
                    <select class="form-select form-select-sm" id="assignee_user_id" name="assignee_user_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person->id ?>"><?= e($person->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="col-12">
                    <p class="small mb-0">Linked task: <a href="<?= e(base_url('tasks/' . $booking->task_id)) ?>"><?= e($booking->task_title ?: ('#' . $booking->task_id)) ?></a></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'plus-lg') ?> <?= $editing ? 'Save booking' : 'Save booking' ?></button>
        <a class="btn btn-secondary" href="<?= e(base_url($editing ? 'bookings/' . $booking->id : 'bookings')) ?>"><?= icon('x-lg') ?> Cancel</a>
    </div>
</form>

<div class="modal fade" id="inv-add-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="inv-add-form">
            <?= csrf_field() ?>
            <input type="hidden" name="ajax" value="1">
            <div class="modal-header">
                <h5 class="modal-title">Add inventory item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="inv_name">Item name</label>
                    <input class="form-control" id="inv_name" name="name" required maxlength="160" placeholder="e.g. Soft Box">
                </div>
                <div class="mb-3">
                    <label class="form-label">Is this rental or owned?</label>
                    <div class="d-flex gap-3">
                        <label class="qs-check"><input class="form-check-input" type="radio" name="ownership" value="owned" checked> Owned</label>
                        <label class="qs-check"><input class="form-check-input" type="radio" name="ownership" value="rental"> Rental</label>
                    </div>
                </div>
                <div class="mb-0" id="inv-vendor-wrap" hidden>
                    <label class="form-label" for="inv_vendor">Rent from / vendor</label>
                    <input class="form-control" id="inv_vendor" name="vendor_name" maxlength="160" placeholder="e.g. Babu's">
                </div>
                <p class="text-danger small mt-2 mb-0" id="inv-error" hidden></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-aqua"><?= icon('plus-lg') ?> Add item</button>
            </div>
        </form>
    </div>
</div>

<template id="rental-row-template">
    <div class="row g-2 align-items-end mb-2 rental-row">
        <div class="col-md-5">
            <label class="form-label">Equipment (rental inventory)</label>
            <div class="wf-search-select" data-search-select data-name="__NAME__">
                <input class="form-control form-control-sm wf-search-input" type="search" placeholder="Search rental items…" autocomplete="off">
                <div class="wf-search-panel" hidden></div>
                <select class="d-none" data-source>
                    <option value="">Select rental item</option>
                    <?php foreach ($rentalItems as $item): ?>
                        <option value="<?= (int) $item->id ?>" data-vendor="<?= e((string) $item->vendor_name) ?>"><?= e($item->name) ?><?= $item->vendor_name ? ' · ' . e($item->vendor_name) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Rent from</label>
            <input class="form-control form-control-sm rental-from" name="__FROM__" value="">
        </div>
        <div class="col-md-3">
            <label class="form-label">Notes / qty</label>
            <input class="form-control form-control-sm" name="__EQ__" value="" placeholder="e.g. 1 Soft box">
        </div>
        <div class="col-md-1">
            <button class="btn btn-sm btn-soft-danger w-100" type="button" data-remove-rental><?= icon('trash3') ?></button>
        </div>
    </div>
</template>

<style>
.bk-compact .form-label { margin-bottom: 2px; font-size: 11px; }
.bk-compact .wf-card-head { margin-bottom: 6px; }
.qs-sheet { border-color: color-mix(in srgb, #f97316 35%, var(--border)); }
.qs-kicker { text-transform: uppercase; letter-spacing: .06em; font-size: 11px; font-weight: 700; color: #ea580c; }
.qs-section-title { font-size: 13px; font-weight: 800; margin: 0 0 6px; }
.qs-check { display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 600; }
.wf-search-select { position: relative; }
.wf-search-panel {
    position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 2px);
    max-height: 220px; overflow: auto; background: var(--surface); border: 1px solid var(--border);
    border-radius: 8px; box-shadow: 0 8px 20px rgba(15,23,42,.12);
}
.wf-search-option { display: block; width: 100%; text-align: left; border: 0; background: transparent; padding: 8px 10px; color: inherit; }
.wf-search-option:hover, .wf-search-option.is-on { background: var(--surface-hover); }
.wf-search-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.wf-search-chip {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1px solid var(--border); border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 600;
}
.wf-search-chip button { border: 0; background: transparent; color: var(--text-muted); line-height: 1; }
</style>

<script>
(function () {
    var projectSelect = document.getElementById('project_id');
    var companyInput = document.getElementById('checklist_company');
    if (projectSelect && companyInput) {
        projectSelect.addEventListener('change', function () {
            var opt = projectSelect.options[projectSelect.selectedIndex];
            companyInput.value = opt ? (opt.getAttribute('data-client') || '') : '';
        });
    }

    function rentalTakenValues(exceptRoot) {
        var taken = {};
        document.querySelectorAll('#rental-rows .rental-row [data-search-select]').forEach(function (el) {
            if (exceptRoot && el === exceptRoot) return;
            var src = el.querySelector('[data-source]');
            if (!src || !src.value) return;
            taken[String(src.value)] = true;
        });
        return taken;
    }

    function refreshOpenPanels() {
        document.querySelectorAll('[data-search-select]').forEach(function (el) {
            if (typeof el._wfRenderPanel === 'function') {
                var input = el.querySelector('.wf-search-input');
                var panel = el.querySelector('.wf-search-panel');
                if (panel && !panel.hidden) el._wfRenderPanel(input ? input.value : '');
            }
        });
    }

    function initSearchSelect(root) {
        if (!root || root.dataset.ready === '1') return;
        root.dataset.ready = '1';
        var input = root.querySelector('.wf-search-input');
        var panel = root.querySelector('.wf-search-panel');
        var source = root.querySelector('[data-source]');
        var chips = root.querySelector('[data-chips]');
        var multi = root.getAttribute('data-multi') === '1';
        var name = root.getAttribute('data-name') || '';
        var isRental = !!root.closest('.rental-row');

        function selectedValues() {
            return Array.from(source.options).filter(function (o) { return o.selected && o.value; }).map(function (o) { return o.value; });
        }

        function syncHidden() {
            root.querySelectorAll('input[data-hidden-value]').forEach(function (el) { el.remove(); });
            if (multi) {
                selectedValues().forEach(function (value) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = name;
                    hidden.value = value;
                    hidden.setAttribute('data-hidden-value', '1');
                    root.appendChild(hidden);
                });
            } else {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = source.value || '';
                hidden.setAttribute('data-hidden-value', '1');
                root.appendChild(hidden);
                var from = root.closest('.rental-row') && root.closest('.rental-row').querySelector('.rental-from');
                var opt = source.options[source.selectedIndex];
                if (from && opt && opt.getAttribute('data-vendor') && !from.value) {
                    from.value = opt.getAttribute('data-vendor') || '';
                }
            }
        }

        function renderChips() {
            if (!chips) return;
            chips.innerHTML = '';
            Array.from(source.options).forEach(function (opt) {
                if (!opt.selected || !opt.value) return;
                var chip = document.createElement('span');
                chip.className = 'wf-search-chip';
                chip.innerHTML = '<span></span><button type="button" aria-label="Remove">&times;</button>';
                chip.querySelector('span').textContent = opt.textContent;
                chip.querySelector('button').addEventListener('click', function () {
                    opt.selected = false;
                    syncHidden();
                    renderChips();
                    refreshOpenPanels();
                });
                chips.appendChild(chip);
            });
        }

        function renderPanel(filter) {
            var q = (filter || '').toLowerCase().trim();
            var taken = isRental ? rentalTakenValues(root) : {};
            panel.innerHTML = '';
            var count = 0;
            Array.from(source.options).forEach(function (opt) {
                if (!opt.value) return;
                // Hide already-selected items from the dropdown.
                if (opt.selected) return;
                if (isRental && taken[String(opt.value)]) return;
                if (q && opt.textContent.toLowerCase().indexOf(q) === -1) return;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'wf-search-option';
                btn.textContent = opt.textContent;
                btn.addEventListener('click', function () {
                    if (multi) {
                        opt.selected = true;
                        input.value = '';
                    } else {
                        Array.from(source.options).forEach(function (o) { o.selected = false; });
                        opt.selected = true;
                        source.value = opt.value;
                        input.value = opt.textContent;
                        panel.hidden = true;
                    }
                    syncHidden();
                    renderChips();
                    renderPanel(input.value);
                    refreshOpenPanels();
                });
                panel.appendChild(btn);
                count++;
            });
            if (count === 0) {
                panel.innerHTML = '<div class="wf-search-option" style="cursor:default;opacity:.7;">No items left</div>';
                panel.hidden = false;
            } else {
                panel.hidden = false;
            }
        }

        root._wfRenderPanel = renderPanel;

        input.addEventListener('focus', function () { renderPanel(input.value); });
        input.addEventListener('input', function () {
            if (!multi && isRental && input.value === '') {
                Array.from(source.options).forEach(function (o) { o.selected = false; });
                source.value = '';
                syncHidden();
                refreshOpenPanels();
            }
            renderPanel(input.value);
        });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) panel.hidden = true;
        });
        if (!multi) {
            var selected = Array.from(source.options).find(function (o) { return o.selected && o.value; });
            if (selected) input.value = selected.textContent;
        }
        syncHidden();
        renderChips();
    }

    function bootSearch() {
        document.querySelectorAll('[data-search-select]').forEach(initSearchSelect);
    }
    bootSearch();

    var rentalIndex = document.querySelectorAll('#rental-rows .rental-row').length;
    var addRental = document.getElementById('add-rental-row');
    var rentalWrap = document.getElementById('rental-rows');
    var rentalTpl = document.getElementById('rental-row-template');
    if (addRental && rentalWrap && rentalTpl) {
        addRental.addEventListener('click', function () {
            var html = rentalTpl.innerHTML
                .replace('__NAME__', 'rentals[' + rentalIndex + '][item_id]')
                .replace('__FROM__', 'rentals[' + rentalIndex + '][rent_from]')
                .replace('__EQ__', 'rentals[' + rentalIndex + '][equipments]');
            rentalWrap.insertAdjacentHTML('beforeend', html);
            rentalIndex++;
            bootSearch();
            refreshOpenPanels();
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-rental]');
        if (!btn) return;
        var row = btn.closest('.rental-row');
        if (row && document.querySelectorAll('#rental-rows .rental-row').length > 1) {
            row.remove();
            refreshOpenPanels();
        }
    });

    var ownershipRadios = document.querySelectorAll('#inv-add-form [name="ownership"]');
    var vendorWrap = document.getElementById('inv-vendor-wrap');
    ownershipRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            vendorWrap.hidden = radio.value !== 'rental' || !radio.checked;
            if (radio.value === 'rental' && radio.checked) vendorWrap.hidden = false;
            if (radio.value === 'owned' && radio.checked) vendorWrap.hidden = true;
        });
    });

    var invForm = document.getElementById('inv-add-form');
    var invError = document.getElementById('inv-error');
    if (invForm) {
        invForm.addEventListener('submit', function (e) {
            e.preventDefault();
            invError.hidden = true;
            var body = new FormData(invForm);
            fetch(<?= json_encode(base_url('inventory/items')) ?>, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: body
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok) {
                    invError.textContent = data.error || 'Could not save item.';
                    invError.hidden = false;
                    return;
                }
                var item = data.item;
                var ownedSource = document.querySelector('[data-name="owned_item_ids[]"] [data-source]');
                var option = new Option(item.name + (item.vendor_name ? ' · ' + item.vendor_name : ''), item.id, false, false);
                if (item.ownership === 'owned' && ownedSource) {
                    ownedSource.add(option);
                } else {
                    option.setAttribute('data-vendor', item.vendor_name || '');
                    document.querySelectorAll('.rental-row [data-source], #rental-row-template [data-source]').forEach(function (sel) {
                        sel.add(new Option(option.text, option.value, false, false));
                        var last = sel.options[sel.options.length - 1];
                        if (item.vendor_name) last.setAttribute('data-vendor', item.vendor_name);
                    });
                }
                document.querySelectorAll('[data-search-select]').forEach(function (el) { el.dataset.ready = '0'; });
                bootSearch();
                var modal = bootstrap.Modal.getInstance(document.getElementById('inv-add-modal'));
                if (modal) modal.hide();
                invForm.reset();
                vendorWrap.hidden = true;
            }).catch(function () {
                invError.textContent = 'Could not save item.';
                invError.hidden = false;
            });
        });
    }
})();
</script>
