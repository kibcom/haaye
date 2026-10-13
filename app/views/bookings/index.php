<?php
$labels = ['scheduled' => 'Scheduled', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Projects</p>
        <h2>Bookings</h2>
        <p class="text-muted mb-0">Meetings and reserved time that belong to a project.</p>
    </div>
    <?php if (can('bookings.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('bookings/create')) ?>"><?= icon('plus-lg') ?> New booking</a>
    <?php endif; ?>
</div>

<form class="wf-filters" method="get">
    <select class="form-select" name="project">
        <option value="">All projects</option>
        <?php foreach ($projects as $project): ?>
            <option value="<?= (int) $project->id ?>" <?= selected($projectId ?? 0, $project->id) ?>><?= e($project->name) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <?php foreach ($labels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= selected($status ?? '', $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($bookings)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No bookings yet',
            'message' => 'Add a meeting or reserved time on one of your projects.',
            'actionUrl' => can('bookings.create') ? 'bookings/create' : null,
            'actionLabel' => can('bookings.create') ? 'New booking' : null,
        ]); ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle">
                <thead>
                <tr>
                    <th>Booking</th>
                    <th>Project</th>
                    <th>When</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td>
                            <a href="<?= e(base_url('bookings/' . $booking->id)) ?>"><strong><?= e($booking->title) ?></strong></a>
                            <div class="text-muted small"><?= e($booking->contact_name ?: $booking->location ?: '') ?></div>
                        </td>
                        <td>
                            <?php if (can('projects.view') || can('projects.view_all')): ?>
                                <a href="<?= e(base_url('projects/' . $booking->project_id)) ?>"><?= e($booking->project_name) ?></a>
                            <?php else: ?>
                                <?= e($booking->project_name) ?>
                            <?php endif; ?>
                            <div class="text-muted small"><?= e($booking->client_name ?: '') ?></div>
                        </td>
                        <td>
                            <?= e(format_datetime($booking->starts_at)) ?>
                            <div class="text-muted small">until <?= e(format_datetime($booking->ends_at, 'g:i A')) ?></div>
                        </td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $booking->status]); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
