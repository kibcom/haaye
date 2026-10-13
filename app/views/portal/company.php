<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Portal</p>
        <h2><?= $client ? e($client->name) : 'Company' ?></h2>
        <p class="text-muted mb-0">Your company profile.</p>
    </div>
</div>

<?php if (!$client): ?>
    <section class="wf-card">
        <p class="mb-0">This login is not linked to a company yet.</p>
    </section>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <section class="wf-card">
                <dl class="wf-dl mb-0">
                    <div><dt>Email</dt><dd><?= e($client->email ?: '') ?></dd></div>
                    <div><dt>Phone</dt><dd><?= e($client->phone ?: '') ?></dd></div>
                    <div><dt>Website</dt><dd><?= e($client->website ?: '') ?></dd></div>
                    <div><dt>Industry</dt><dd><?= e($client->industry ?: '') ?></dd></div>
                    <div><dt>Address</dt><dd><?= e(trim(($client->address ?: '') . ' ' . ($client->city ?: '') . ' ' . ($client->country ?: '')) ?: '') ?></dd></div>
                </dl>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="wf-card">
                <div class="wf-card-head"><h3>Contacts</h3></div>
                <?php if ($contacts === []): ?>
                    <p class="text-muted mb-0">No contacts listed.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($contacts as $contact): ?>
                            <li class="mb-2">
                                <strong><?= e($contact->name) ?></strong>
                                <div class="text-muted small"><?= e($contact->job_title ?: 'Contact') ?><?= $contact->email ? ' · ' . e($contact->email) : '' ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
