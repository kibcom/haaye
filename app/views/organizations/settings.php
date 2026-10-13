<?php
$startEditing = $canEdit && errors() !== [];
$savedLogo = trim((string) ($organization->logo ?? ''));
$logoRemoved = (string) old('remove_logo', '0') === '1';
$showLogo = $savedLogo !== '' && !$logoRemoved;
?>
<?php \App\Core\View::component('settings-nav', []); ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Settings</p>
        <h2>Organization</h2>
        <p class="text-muted mb-0">Plan, access, and payments for <?= e($organization->name) ?>.</p>
    </div>
</div>

<section class="wf-card mb-4">
    <div class="wf-card-head">
        <h3><?= icon('credit-card') ?> Billing plan</h3>
        <div class="d-flex align-items-center gap-2">
            <?php \App\Core\View::component('status-badge', ['status' => $organization->status]); ?>
            <?php if ($canEdit): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(base_url('settings/organization/plan')) ?>"><?= icon('arrow-up-circle') ?> <?= $upgrade ? 'View request' : 'Update plan' ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="wf-billing-stats">
        <div>
            <span>Plan</span>
            <strong><?= e($billing['name']) ?></strong>
            <small><?= e($billing['code']) ?></small>
        </div>
        <div>
            <span>Price</span>
            <?php if ($billing['priced'] && (float) $billing['price'] > 0): ?>
                <strong><?= e(money($billing['currency'], $billing['price'])) ?></strong>
                <small>per month</small>
            <?php elseif ($billing['priced']): ?>
                <strong>Free</strong>
                <small>no subscription charge</small>
            <?php else: ?>
                <strong></strong>
                <small>set by the platform</small>
            <?php endif; ?>
        </div>
        <div>
            <span>Workspaces</span>
            <strong><?= $billing['workspaces'] === null ? 'Unlimited' : (int) $billing['workspaces'] ?></strong>
            <small><?= $billing['workspaces'] === null ? 'no plan limit' : 'included on this plan' ?></small>
        </div>
        <div>
            <span>Renews</span>
            <strong><?= $billing['renews_on'] ? e(format_date($billing['renews_on'])) : '' ?></strong>
            <small><?= $billing['renews_on'] ? 'next billing date' : 'no upcoming charge' ?></small>
        </div>
    </div>
    <?php if ($upgrade): ?>
        <p class="text-muted mt-3 mb-0">A request to move to <?= e((string) $upgrade['requested_plan_name']) ?> is waiting for the platform owner to check the payment.</p>
    <?php endif; ?>
</section>

<section class="wf-card mb-4">
    <div class="wf-card-head">
        <h3><?= icon('unlock') ?> Access on this plan</h3>
    </div>
    <p class="text-muted">These are the functions included with <?= e($billing['name']) ?>.</p>
    <ul class="wf-plan-access">
        <?php foreach ($billing['access'] as $feature): ?>
            <li class="<?= $feature['included'] ? 'is-on' : 'is-off' ?>">
                <?= icon($feature['included'] ? 'check-circle' : 'x-circle') ?>
                <span>
                    <strong><?= e($feature['label']) ?></strong>
                    <small><?= e($feature['detail']) ?></small>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="wf-card mb-4">
    <div class="wf-card-head">
        <h3><?= icon('receipt') ?> Payment history</h3>
    </div>
    <?php if ($billing['payments'] === []): ?>
        <p class="text-muted mb-0"><?= $billing['priced'] && (float) $billing['price'] <= 0 ? 'This plan has no subscription charges.' : 'No subscription payments yet.' ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table wf-keep align-middle mb-0">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Period</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($billing['payments'] as $payment): ?>
                    <tr>
                        <td><?= e(format_date($payment['paid_on'])) ?></td>
                        <td><?= e(format_date($payment['period_start'])) ?> – <?= e(format_date($payment['period_end'])) ?></td>
                        <td><?= e($payment['plan']) ?></td>
                        <td><?= e(money($payment['currency'], $payment['amount'])) ?></td>
                        <td><?= e($payment['method']) ?></td>
                        <td><?= e($payment['reference']) ?></td>
                        <td><?php \App\Core\View::component('status-badge', ['status' => $payment['status']]); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="wf-card" id="company-details">
    <div class="wf-card-head">
        <div>
            <h3><?= icon('building') ?> Company details</h3>
            <p class="text-muted mb-0">Name, contact, logo, invoices, and timesheets for this company.</p>
        </div>
        <?php if ($canEdit): ?>
            <button class="btn btn-soft-primary" type="button" id="org-edit" <?= $startEditing ? 'hidden' : '' ?>><?= icon('pencil') ?> Edit</button>
        <?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= e(base_url('settings/organization')) ?>" id="org-settings-form">
        <?= csrf_field() ?>
        <fieldset id="org-fields" <?= $canEdit && $startEditing ? '' : 'disabled' ?>>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">Company name</label>
                    <input class="form-control" id="name" name="name" value="<?= e((string) old('name', $organization->name)) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="industry">Industry</label>
                    <input class="form-control" id="industry" name="industry" value="<?= e((string) old('industry', $organization->industry)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" type="email" name="email" value="<?= e((string) old('email', $organization->email)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="<?= e((string) old('phone', $organization->phone)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="website">Website</label>
                    <input class="form-control" id="website" name="website" value="<?= e((string) old('website', $organization->website)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="logo">Logo</label>
                    <div class="wf-logo-field">
                        <div class="wf-logo-preview" id="org-logo-preview" <?= $showLogo ? '' : 'hidden' ?>>
                            <img <?= $showLogo ? 'src="' . e(asset($savedLogo)) . '"' : '' ?> data-saved="<?= e($savedLogo !== '' ? asset($savedLogo) : '') ?>" alt="<?= e($organization->name) ?> logo">
                        </div>
                        <input type="hidden" name="remove_logo" id="org-remove-logo" value="<?= $logoRemoved ? '1' : '0' ?>">
                        <input class="form-control" id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                        <?php if ($canEdit): ?>
                            <button class="btn btn-sm btn-soft-danger" type="button" id="org-logo-remove" <?= $startEditing && $showLogo ? '' : 'hidden' ?>><?= icon('trash3') ?> Remove logo</button>
                        <?php endif; ?>
                    </div>
                    <div class="form-text">JPG, PNG, or WEBP. Up to 2 MB.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="address">Address</label>
                    <input class="form-control" id="address" name="address" value="<?= e((string) old('address', $organization->address)) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" value="<?= e((string) old('city', $organization->city)) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="country">Country</label>
                    <input class="form-control" id="country" name="country" value="<?= e((string) old('country', $organization->country)) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="currency">Currency</label>
                    <input class="form-control" id="currency" name="currency" maxlength="3" value="<?= e((string) old('currency', $organization->currency)) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="invoice_prefix">Invoice prefix</label>
                    <input class="form-control" id="invoice_prefix" name="invoice_prefix" maxlength="12" value="<?= e((string) old('invoice_prefix', $organization->invoice_prefix)) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="invoice_next_number">Next invoice number</label>
                    <input class="form-control" id="invoice_next_number" name="invoice_next_number" inputmode="numeric" value="<?= e((string) old('invoice_next_number', (string) $organization->invoice_next_number)) ?>" required>
                    <div class="form-text">The next draft will use this number, for example <?= e($organization->invoice_prefix) ?>-<?= e(str_pad((string) $organization->invoice_next_number, 4, '0', STR_PAD_LEFT)) ?>.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="timezone">Timezone</label>
                    <input class="form-control" id="timezone" name="timezone" value="<?= e((string) old('timezone', $organization->timezone)) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tax_id">Tax ID</label>
                    <input class="form-control" id="tax_id" name="tax_id" value="<?= e((string) old('tax_id', $organization->tax_id)) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="tax_information">Tax information</label>
                    <textarea class="form-control" id="tax_information" name="tax_information" rows="3"><?= e((string) old('tax_information', $organization->tax_information)) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Timesheets</label>
                    <div class="form-check form-switch">
                        <input type="hidden" name="show_time" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="show_time" name="show_time" value="1" <?= (int) old('show_time', (string) $organization->show_time) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_time">Show timesheets</label>
                    </div>
                    <div class="form-text">Off hides the timesheet, timer, and time reports for this company. Turn it on when you want people to log hours.</div>
                </div>
            </div>
        </fieldset>
        <?php if ($canEdit): ?>
            <div class="mt-4 d-flex gap-2" id="org-save" <?= $startEditing ? '' : 'hidden' ?>>
                <button class="btn btn-primary" type="submit"><?= icon('check-lg') ?> Save settings</button>
                <button class="btn btn-secondary" type="button" id="org-cancel"><?= icon('x-lg') ?> Cancel</button>
            </div>
        <?php endif; ?>
    </form>
</section>
<?php if ($canEdit): ?>
<script>
(function () {
    var edit = document.getElementById('org-edit');
    var fields = document.getElementById('org-fields');
    var save = document.getElementById('org-save');
    var cancel = document.getElementById('org-cancel');
    var form = document.getElementById('org-settings-form');
    var logoInput = document.getElementById('logo');
    var logoPreview = document.getElementById('org-logo-preview');
    var logoImage = logoPreview ? logoPreview.querySelector('img') : null;
    var logoRemove = document.getElementById('org-logo-remove');
    var logoFlag = document.getElementById('org-remove-logo');
    var savedLogo = logoImage ? (logoImage.getAttribute('data-saved') || '') : '';
    var logoObjectUrl = '';
    if (!edit || !fields || !save || !form) return;

    function paintLogo(src) {
        if (!logoPreview || !logoImage) return;
        if (src) {
            logoImage.src = src;
            logoPreview.hidden = false;
        } else {
            logoImage.removeAttribute('src');
            logoPreview.hidden = true;
        }
        if (logoRemove) logoRemove.hidden = fields.disabled || !src;
    }

    function clearChosenFile() {
        if (logoObjectUrl) {
            URL.revokeObjectURL(logoObjectUrl);
            logoObjectUrl = '';
        }
        if (logoInput) logoInput.value = '';
    }

    edit.addEventListener('click', function () {
        fields.disabled = false;
        save.hidden = false;
        edit.hidden = true;
        paintLogo(logoFlag && logoFlag.value === '1' ? '' : (logoObjectUrl || savedLogo));
        var first = fields.querySelector('input:not([type="hidden"]), textarea, select');
        if (first) first.focus();
    });

    if (logoInput) {
        logoInput.addEventListener('change', function () {
            if (logoObjectUrl) {
                URL.revokeObjectURL(logoObjectUrl);
                logoObjectUrl = '';
            }
            var file = logoInput.files && logoInput.files[0];
            if (!file) {
                paintLogo(logoFlag && logoFlag.value === '1' ? '' : savedLogo);
                return;
            }
            if (logoFlag) logoFlag.value = '0';
            logoObjectUrl = URL.createObjectURL(file);
            paintLogo(logoObjectUrl);
        });
    }

    if (logoRemove) {
        logoRemove.addEventListener('click', function () {
            clearChosenFile();
            if (logoFlag) logoFlag.value = savedLogo ? '1' : '0';
            paintLogo('');
        });
    }

    if (cancel) {
        cancel.addEventListener('click', function () {
            clearChosenFile();
            form.reset();
            fields.disabled = true;
            save.hidden = true;
            edit.hidden = false;
            paintLogo(logoFlag && logoFlag.value === '1' ? '' : savedLogo);
        });
    }
})();
</script>
<?php endif; ?>
