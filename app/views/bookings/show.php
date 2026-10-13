<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Projects</p>
        <h2><?= e($booking->title) ?></h2>
        <p class="text-muted mb-0">
            <?php if (can('projects.view') || can('projects.view_all')): ?>
                <a href="<?= e(base_url('projects/' . $booking->project_id)) ?>"><?= e($booking->project_name) ?></a>
            <?php else: ?>
                <?= e($booking->project_name) ?>
            <?php endif; ?>
            <?php if ($booking->client_name): ?> · <?= e($booking->client_name) ?><?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($canEdit)): ?>
            <a class="btn btn-soft-primary" href="<?= e(base_url('bookings/' . $booking->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
            <form method="post" action="<?= e(base_url('bookings/' . $booking->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this booking?">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit"><?= icon('trash3') ?> Delete</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Booking</h3></div>
            <dl class="wf-dl">
                <div><dt>Status</dt><dd><?php \App\Core\View::component('status-badge', ['status' => $booking->status]); ?></dd></div>
                <div><dt>Starts</dt><dd><?= e(format_datetime($booking->starts_at)) ?></dd></div>
                <div><dt>Ends</dt><dd><?= e(format_datetime($booking->ends_at)) ?></dd></div>
                <div><dt>Contact</dt><dd><?= e($booking->contact_name ?: '') ?></dd></div>
                <div><dt>Location</dt><dd><?= e($booking->location ?: '') ?></dd></div>
            </dl>
            <?php if ($booking->notes): ?>
                <p class="mt-3 mb-0"><?= nl2br(e($booking->notes)) ?></p>
            <?php endif; ?>
            <?php if (!empty($booking->task_id)): ?>
                <p class="mt-3 mb-0">
                    Linked task:
                    <a href="<?= e(base_url('tasks/' . $booking->task_id)) ?>"><?= e($booking->task_title ?: ('Task #' . $booking->task_id)) ?></a>
                    · <a href="<?= e(base_url('work-flow')) ?>">Workflow</a>
                </p>
            <?php endif; ?>
        </section>

        <?php
        $checklist = \App\Services\BookingChecklist::fromBooking($booking);
        $selected = $selectedEquipment ?? ['owned_ids' => [], 'rentals' => []];
        $ownedMap = $ownedMap ?? [];
        $rentalMap = $rentalMap ?? [];
        $statusLabel = match ($booking->checklist_status ?? 'draft') {
            'pending_approval' => 'Pending approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Draft',
        };
        ?>
        <section class="wf-card mt-3">
            <div class="wf-card-head">
                <h3>Quick-Start Checklist</h3>
                <span class="badge text-bg-light"><?= e($statusLabel) ?></span>
            </div>
            <dl class="wf-dl mb-3">
                <div><dt>Company</dt><dd><?= e($booking->checklist_company ?: ($booking->client_name ?: '')) ?></dd></div>
                <div><dt>Date</dt><dd><?= e(($booking->checklist_date ?: $checklist['date'] ?? '') ? format_date((string) ($booking->checklist_date ?: $checklist['date'])) : '') ?></dd></div>
                <div><dt>Represented</dt><dd><?= e($checklist['represented_name'] ?: '') ?><?= !empty($checklist['represented_signed']) ? ' · Signed' : '' ?></dd></div>
                <?php if (!empty($booking->submitted_to_name)): ?>
                    <div><dt>Submitted to</dt><dd><?= e($booking->submitted_to_name) ?><?= $booking->submitted_at ? ' · ' . e(format_datetime($booking->submitted_at)) : '' ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($booking->reviewed_by_name)): ?>
                    <div><dt>Reviewed by</dt><dd><?= e($booking->reviewed_by_name) ?><?= $booking->review_note ? ' · ' . e($booking->review_note) : '' ?></dd></div>
                <?php endif; ?>
            </dl>

            <h4 class="h6">Owned equipment</h4>
            <?php if (empty($selected['owned_ids'])): ?>
                <p class="text-muted small">No owned items selected.</p>
            <?php else: ?>
                <ul class="mb-3">
                    <?php foreach ($selected['owned_ids'] as $itemId): ?>
                        <?php $item = $ownedMap[(int) $itemId] ?? null; ?>
                        <li><?= e($item?->name ?: ('Item #' . $itemId)) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h4 class="h6">Rentals</h4>
            <ul class="mb-0">
                <?php
                $hasRental = false;
                foreach ($selected['rentals'] as $rental):
                    $item = !empty($rental['item_id']) ? ($rentalMap[(int) $rental['item_id']] ?? null) : null;
                    $label = $item?->name ?: ($rental['equipments'] ?? '');
                    $from = $rental['rent_from'] ?: ($item?->vendor_name ?: '');
                    if ($label === '' && $from === '') {
                        continue;
                    }
                    $hasRental = true;
                    ?>
                    <li><strong><?= e($from ?: 'Vendor') ?></strong> — <?= e($label) ?></li>
                <?php endforeach; ?>
                <?php if (!$hasRental): ?>
                    <li class="text-muted">No rentals listed.</li>
                <?php endif; ?>
            </ul>
        </section>

        <?php if (!empty($canSubmit) && ($booking->checklist_status ?? 'draft') !== 'pending_approval'): ?>
            <section class="wf-card mt-3">
                <div class="wf-card-head"><h3>Send for approval</h3></div>
                <form method="post" action="<?= e(base_url('bookings/' . $booking->id . '/submit')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-md-8">
                        <label class="form-label" for="submit_to_user_id">CEO / Production Manager</label>
                        <select class="form-select form-select-sm" id="submit_to_user_id" name="submit_to_user_id" required>
                            <option value="">Select approver</option>
                            <?php foreach ($approvers ?? [] as $person): ?>
                                <option value="<?= (int) $person->id ?>"><?= e($person->name) ?> · <?= e($person->role_name ?: $person->role_slug) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-aqua w-100" type="submit"><?= icon('send') ?> Submit</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if (!empty($canApprove) && ($booking->checklist_status ?? '') === 'pending_approval'): ?>
            <section class="wf-card mt-3">
                <div class="wf-card-head"><h3>Review checklist</h3></div>
                <form method="post" action="<?= e(base_url('bookings/' . $booking->id . '/review')) ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label" for="review_note">Note</label>
                        <input class="form-control form-control-sm" id="review_note" name="review_note" maxlength="255">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success" type="submit" name="decision" value="approved"><?= icon('check-lg') ?> Approve</button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-warning" type="submit" name="decision" value="rejected"><?= icon('x-lg') ?> Reject</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
    <?php if (!empty($canEdit) && $booking->status !== 'cancelled'): ?>
        <div class="col-lg-5">
            <section class="wf-card">
                <div class="wf-card-head"><h3>Update status</h3></div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($booking->status !== 'confirmed'): ?>
                        <form method="post" action="<?= e(base_url('bookings/' . $booking->id . '/status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="confirmed">
                            <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Confirm</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($booking->status !== 'completed'): ?>
                        <form method="post" action="<?= e(base_url('bookings/' . $booking->id . '/status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="completed">
                            <button class="btn btn-success" type="submit"><?= icon('check-circle') ?> Complete</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(base_url('bookings/' . $booking->id . '/status')) ?>" data-confirm="Are you sure you want to cancel this booking?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-warning" type="submit"><?= icon('x-circle') ?> Cancel booking</button>
                    </form>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>
