<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Organizations</p>
        <h2><?= e($organization->name) ?></h2>
        <p class="text-muted mb-0"><?= e($organization->company_code ?: 'No company ID') ?> · <?= e($organization->industry ?: 'Organization') ?> · <?= e(\App\Services\FeatureService::planNames()[$organization->subscription_plan] ?? $organization->subscription_plan) ?></p>
    </div>
    <a class="btn btn-soft-primary" href="<?= e(base_url('platform/organizations/' . $organization->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <section class="wf-card">
            <dl class="wf-dl">
                <div><dt>Status</dt><dd><?php \App\Core\View::component('status-badge', ['status' => $organization->status]); ?></dd></div>
                <div><dt>Email</dt><dd><?= e($organization->email ?: '') ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($organization->phone ?: '') ?></dd></div>
                <div><dt>Country</dt><dd><?= e($organization->country ?: '') ?></dd></div>
                <div><dt>Currency</dt><dd><?= e($organization->currency) ?></dd></div>
                <div><dt>Timezone</dt><dd><?= e($organization->timezone) ?></dd></div>
                <div><dt>Created</dt><dd><?= e(format_datetime($organization->created_at)) ?></dd></div>
                <div>
                    <dt>Features</dt>
                    <dd>
                        <?php $savedFeatures = \App\Services\FeatureService::saved($organization); ?>
                        <?php if ($savedFeatures === null): ?>
                            All features are on until you limit this company.
                        <?php elseif ($savedFeatures === []): ?>
                            No features are enabled.
                        <?php else: ?>
                            <?php
                            $labels = [];
                            foreach ($savedFeatures as $slug) {
                                $labels[] = \App\Services\FeatureService::catalog()[$slug]['label'] ?? $slug;
                            }
                            echo e(implode(', ', $labels));
                            ?>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="wf-card">
            <div class="wf-card-head"><h3>People</h3></div>
            <?php if (empty($users)): ?>
                <p class="text-muted mb-0">No users in this organization.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table wf-table align-middle">
                        <thead>
                        <tr><th>Name</th><th>Role</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $person): ?>
                            <tr>
                                <td>
                                    <strong><?= e($person->name) ?></strong>
                                    <div class="text-muted small"><?= e($person->email) ?></div>
                                </td>
                                <td><?= e($person->role_name ?? '') ?></td>
                                <td><?php \App\Core\View::component('status-badge', ['status' => $person->status]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
