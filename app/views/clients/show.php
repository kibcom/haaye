<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Client dashboard</p>
        <h2><?= e($client->name) ?></h2>
        <p class="text-muted mb-0"><?= e($client->industry ?: 'Client') ?> · <?= e($client->country ?: 'No country set') ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('projects.create')): ?>
            <a class="btn btn-aqua" href="<?= e(base_url('projects/create?client=' . (int) $client->id)) ?>"><?= icon('plus-lg') ?> New project</a>
        <?php endif; ?>
        <?php if (can('clients.update')): ?>
            <a class="btn btn-soft-primary" href="<?= e(base_url('clients/' . $client->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
        <?php endif; ?>
        <?php if (can('clients.activate')): ?>
            <?php if ($client->status === 'archived'): ?>
                <form method="post" action="<?= e(base_url('clients/' . $client->id . '/activate')) ?>" data-confirm="Are you sure you want to restore this client?">
                    <?= csrf_field() ?>
                    <button class="btn btn-soft-success" type="submit"><?= icon('arrow-counterclockwise') ?> Restore</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(base_url('clients/' . $client->id . '/archive')) ?>" data-confirm="Are you sure you want to archive this client?">
                    <?= csrf_field() ?>
                    <button class="btn btn-warning" type="submit"><?= icon('archive') ?> Archive</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="wf-stat"><span>Status</span><strong><?= e(ucfirst($client->status)) ?></strong></div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat"><span>Contacts</span><strong><?= (int) $contactCount ?></strong></div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat"><span>Projects</span><strong><?= (int) $projectCount ?></strong></div>
    </div>
    <div class="col-md-3">
        <div class="wf-stat"><span>Assigned to</span><strong><?= e($client->assigned_user_name ?: '') ?></strong></div>
    </div>
</div>

<ul class="nav nav-tabs wf-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview" type="button">Overview</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#contacts" type="button">Contacts</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#projects" type="button">Projects</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="overview">
        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Company details</h3></div>
                    <dl class="wf-dl">
                        <div><dt>Contact</dt><dd><?= e($client->contact_name ?: '') ?></dd></div>
                        <div><dt>Email</dt><dd><?= e($client->email ?: '') ?></dd></div>
                        <div><dt>Phone</dt><dd><?= e($client->phone ?: '') ?></dd></div>
                        <div><dt>Website</dt><dd>
                            <?php
                            $website = (string) $client->website;
                            $safeWebsite = preg_match('/^https?:\/\//i', $website) === 1;
                            ?>
                            <?= $safeWebsite ? '<a href="' . e($website) . '" target="_blank" rel="noopener">' . e($website) . '</a>' : e($website !== '' ? $website : '') ?>
                        </dd></div>
                        <div><dt>Address</dt><dd><?= e(trim(($client->address ?: '') . ' ' . ($client->city ?: '')) ?: '') ?></dd></div>
                        <div><dt>Tax ID</dt><dd><?= e($client->tax_id ?: '') ?></dd></div>
                        <div><dt>Created</dt><dd><?= e(format_datetime($client->created_at)) ?></dd></div>
                    </dl>
                    <?php if ($client->notes): ?>
                        <p class="mt-3 mb-0"><?= nl2br(e($client->notes)) ?></p>
                    <?php endif; ?>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="wf-card">
                    <div class="wf-card-head"><h3>Recent activity</h3></div>
                    <?php if (empty($activity)): ?>
                        <?php \App\Core\View::component('empty-state', [
                            'title' => 'No activity yet',
                            'message' => 'Updates to this client will appear here.',
                        ]); ?>
                    <?php else: ?>
                        <ul class="wf-timeline">
                            <?php foreach ($activity as $item): ?>
                                <li>
                                    <strong><?= e($item->actor_name ?: 'System') ?></strong>
                                    <span><?= e($item->description) ?></span>
                                    <time><?= e(format_datetime($item->created_at)) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="contacts">
        <div class="row g-3 mt-1">
            <div class="col-lg-7">
                <section class="wf-card">
                    <div class="wf-card-head"><h3>People</h3></div>
                    <?php if (empty($contacts)): ?>
                        <?php \App\Core\View::component('empty-state', [
                            'title' => 'No contacts yet',
                            'message' => 'Add the people you work with at this client.',
                        ]); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table wf-table align-middle">
                                <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Title</th>
                                    <th>Email</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($contacts as $contact): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($contact->name) ?></strong>
                                            <?php if ($contact->is_primary): ?>
                                                <span class="badge text-bg-info wf-badge">Primary</span>
                                            <?php endif; ?>
                                            <div class="text-muted small"><?= e($contact->phone ?: '') ?></div>
                                        </td>
                                        <td><?= e($contact->job_title ?: '') ?></td>
                                        <td><?= e($contact->email ?: '') ?></td>
                                        <td class="text-end">
                                            <?php if (can('clients.contacts.manage')): ?>
                                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                                    <a class="btn btn-sm btn-soft-primary" href="<?= e(base_url('clients/' . $client->id . '/contacts/' . $contact->id . '/edit')) ?>"><?= icon('pencil') ?> Edit</a>
                                                    <?php if (!$contact->is_primary): ?>
                                                        <form method="post" action="<?= e(base_url('clients/' . $client->id . '/contacts/' . $contact->id . '/primary')) ?>" data-confirm="Are you sure you want to make this the primary contact?">
                                                            <?= csrf_field() ?>
                                                            <button class="btn btn-sm btn-soft-primary" type="submit"><?= icon('star') ?> Make primary</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" action="<?= e(base_url('clients/' . $client->id . '/contacts/' . $contact->id . '/delete')) ?>" data-confirm="Are you sure you want to remove this contact?">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm btn-soft-danger" type="submit"><?= icon('trash3') ?> Remove</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <?php if (can('clients.contacts.manage')): ?>
                <div class="col-lg-5">
                    <section class="wf-card">
                        <div class="wf-card-head"><h3>Add contact</h3></div>
                        <form method="post" action="<?= e(base_url('clients/' . $client->id . '/contacts')) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="contact_name">Name</label>
                                <input class="form-control" id="contact_name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="contact_email">Email</label>
                                <input class="form-control" id="contact_email" type="email" name="email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="contact_phone">Phone</label>
                                <input class="form-control" id="contact_phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="contact_job_title">Job title</label>
                                <input class="form-control" id="contact_job_title" name="job_title">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="contact_notes">Notes</label>
                                <input class="form-control" id="contact_notes" name="notes">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="is_primary" name="is_primary">
                                <label class="form-check-label" for="is_primary">Primary contact</label>
                            </div>
                            <button class="btn btn-primary" type="submit"><?= icon('person-plus') ?> Add contact</button>
                        </form>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="projects">
        <section class="wf-card mt-3">
            <div class="wf-card-head">
                <h3>Linked projects</h3>
                <?php if (can('projects.create')): ?>
                    <a class="btn btn-sm btn-aqua" href="<?= e(base_url('projects/create?client=' . (int) $client->id)) ?>"><?= icon('plus-lg') ?> New project</a>
                <?php endif; ?>
            </div>
            <?php if (empty($projects)): ?>
                <?php \App\Core\View::component('empty-state', [
                    'title' => 'No projects yet',
                    'message' => 'Projects for this client will be listed here.',
                    'actionUrl' => can('projects.create') ? 'projects/create?client=' . $client->id : null,
                    'actionLabel' => can('projects.create') ? 'New project' : null,
                ]); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table wf-table align-middle">
                        <thead>
                        <tr>
                            <th>Project</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Due date</th>
                            <th>Progress</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($projects as $project): ?>
                            <?php $canOpenProject = \App\Services\ProjectAccess::canView($project); ?>
                            <tr>
                                <td>
                                    <?php if ($canOpenProject): ?>
                                        <a href="<?= e(base_url('projects/' . $project->id)) ?>"><strong><?= e($project->name) ?></strong></a>
                                    <?php else: ?>
                                        <strong><?= e($project->name) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($project->code ?: '') ?></td>
                                <td><?php \App\Core\View::component('status-badge', ['status' => $project->status]); ?></td>
                                <td><?php \App\Core\View::component('status-badge', ['status' => $project->priority]); ?></td>
                                <td><?= e(format_date($project->due_date)) ?></td>
                                <td><?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
