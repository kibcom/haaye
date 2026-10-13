<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2>Platform users</h2>
        <p class="text-muted mb-0">Super administrators for <?= e(app_name()) ?> itself.</p>
    </div>
    <?php if (can('platform.users.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('platform/users/create')) ?>"><?= icon('person-plus') ?> Add platform user</a>
    <?php endif; ?>
</div>

<section class="wf-card">
    <div class="table-responsive">
        <table class="table wf-table align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Last login</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $person): ?>
                <tr>
                    <td><strong><?= e($person->name) ?></strong></td>
                    <td><?= e($person->email) ?></td>
                    <td><?php \App\Core\View::component('status-badge', ['status' => $person->status]); ?></td>
                    <td><?= e(format_datetime($person->last_login_at)) ?></td>
                    <td><a href="<?= e(base_url('platform/users/' . $person->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?>
</section>
