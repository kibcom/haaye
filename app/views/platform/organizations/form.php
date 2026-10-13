<?php $editing = $organization !== null; ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Platform</p>
        <h2><?= $editing ? 'Edit organization' : 'New organization' ?></h2>
    </div>
</div>

<section class="wf-card">
    <form method="post" enctype="multipart/form-data" action="<?= e(base_url($editing ? 'platform/organizations/' . $organization->id : 'platform/organizations')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Company name</label>
                <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $organization->name ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="industry">Industry</label>
                <input class="form-control" id="industry" name="industry" value="<?= e((string) old('industry', $organization->industry ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email', $organization->email ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $organization->phone ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="website">Website</label>
                <input class="form-control" id="website" name="website" value="<?= e((string) old('website', $organization->website ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="logo">Logo</label>
                <input class="form-control" id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="address">Address</label>
                <input class="form-control" id="address" name="address" value="<?= e((string) old('address', $organization->address ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="city">City</label>
                <input class="form-control" id="city" name="city" value="<?= e((string) old('city', $organization->city ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="country">Country</label>
                <input class="form-control" id="country" name="country" value="<?= e((string) old('country', $organization->country ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="currency">Currency</label>
                <input class="form-control" id="currency" name="currency" maxlength="3" value="<?= e((string) old('currency', $organization->currency ?? 'USD')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="timezone">Timezone</label>
                <input class="form-control" id="timezone" name="timezone" value="<?= e((string) old('timezone', $organization->timezone ?? 'UTC')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tax_id">Tax ID</label>
                <input class="form-control" id="tax_id" name="tax_id" value="<?= e((string) old('tax_id', $organization->tax_id ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (['active', 'inactive', 'suspended'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected(old('status', $organization->status ?? 'active'), $status) ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-0" for="subscription_plan">Plan</label>
                    <?php if (can('platform.plans.update')): ?>
                        <a class="small" href="<?= e(base_url('platform/plans')) ?>">Edit plans</a>
                    <?php endif; ?>
                </div>
                <select class="form-select mt-1" id="subscription_plan" name="subscription_plan" data-plans="<?= e((string) json_encode(\App\Services\FeatureService::plans())) ?>">
                    <?php foreach (\App\Services\FeatureService::planNames() as $plan => $planName): ?>
                        <option value="<?= e($plan) ?>" <?= selected(old('subscription_plan', $organization->subscription_plan ?? 'FREE'), $plan) ?>><?= e($planName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $featureCatalog = \App\Services\FeatureService::catalog();
            $postedFeatures = old('features', null);
            if (old('features_submitted', '') !== '') {
                $selectedFeatures = is_array($postedFeatures) ? $postedFeatures : [];
            } elseif ($editing && \App\Services\FeatureService::saved($organization) === null) {
                $selectedFeatures = array_keys($featureCatalog);
            } elseif ($editing) {
                $selectedFeatures = \App\Services\FeatureService::saved($organization) ?? [];
            } else {
                $selectedFeatures = \App\Services\FeatureService::forPlan((string) old('subscription_plan', 'FREE'));
            }
            ?>
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <label class="form-label mb-0">Features</label>
                        <?php if ($editing): ?>
                            <a class="small ms-2" href="<?= e(base_url('platform/guide?organization=' . $organization->id)) ?>">How it works</a>
                        <?php endif; ?>
                        <div class="form-check form-switch mt-2 mb-1">
                            <input type="hidden" name="show_guide" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="show_guide" name="show_guide" value="1" <?= (int) old('show_guide', $organization?->show_guide ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_guide">Show How it works under Settings</label>
                        </div>
                        <p class="text-muted small mb-2">The plan checks its features. Leave extra boxes checked to add them, or clear a box to remove that function from this company.</p>
                    </div>
                    <button class="btn btn-outline btn-sm" type="button" id="feature-match-plan"><?= icon('check2') ?> Use only this plan</button>
                </div>
                <input type="hidden" name="features_submitted" value="1">
                <div class="wf-feature-grid" id="feature-list">
                    <?php foreach ($featureCatalog as $slug => $feature): ?>
                        <label class="wf-feature">
                            <span class="form-check">
                                <input class="form-check-input" type="checkbox" name="features[]" value="<?= e($slug) ?>" data-feature="<?= e($slug) ?>" <?= in_array($slug, $selectedFeatures, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= e($feature['label']) ?></span>
                            </span>
                            <small><?= e($feature['detail']) ?></small>
                            <span class="wf-feature-tag" data-feature-tag="<?= e($slug) ?>"></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="tax_information">Tax information</label>
                <textarea class="form-control" id="tax_information" name="tax_information" rows="3"><?= e((string) old('tax_information', $organization->tax_information ?? '')) ?></textarea>
            </div>
        </div>

        <?php if (!$editing): ?>
            <h3 class="wf-group-title mt-4">Owner account</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="owner_name">Owner name</label>
                    <input class="form-control" id="owner_name" name="owner_name" value="<?= e((string) old('owner_name')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="owner_email">Owner email</label>
                    <input class="form-control" id="owner_email" type="email" name="owner_email" value="<?= e((string) old('owner_email')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="owner_password">Owner password</label>
                    <input class="form-control" id="owner_password" type="password" name="owner_password" minlength="<?= (int) $minPassword ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="owner_password_confirmation">Confirm password</label>
                    <input class="form-control" id="owner_password_confirmation" type="password" name="owner_password_confirmation" required>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= icon($editing ? 'check-lg' : 'plus-lg') ?> <?= $editing ? 'Save' : 'Create organization' ?></button>
            <a class="btn btn-secondary" href="<?= e(base_url('platform/organizations')) ?>"><?= icon('x-lg') ?> Cancel</a>
        </div>
    </form>
</section>
