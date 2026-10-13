<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Production</p>
        <h2>Pipeline workflows</h2>
        <p class="text-muted mb-0">Configurable multi-stage production: templates, project runs, approve → next stage. Pipeline stages create real tasks.</p>
    </div>
    <?php if (!empty($canManageTemplates)): ?>
        <a class="btn btn-primary" href="<?= e(base_url('pipeline/templates/create')) ?>"><?= icon('plus-lg') ?> New template</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Templates</h3></div>
            <?php if (empty($templates)): ?>
                <?php \App\Core\View::component('empty-state', [
                    'title' => 'No templates yet',
                    'message' => 'Create a campaign pipeline (site → shoot → content → edit → social).',
                    'actionUrl' => !empty($canManageTemplates) ? 'pipeline/templates/create' : null,
                    'actionLabel' => !empty($canManageTemplates) ? 'New template' : null,
                ]); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table wf-table align-middle">
                        <thead><tr><th>Name</th><th>Mode</th><th>Stages</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($templates as $tpl): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(base_url('pipeline/templates/' . $tpl->id)) ?>"><strong><?= e($tpl->name) ?></strong></a>
                                    <?php if (!(int) $tpl->is_active): ?><span class="text-muted small"> · inactive</span><?php endif; ?>
                                </td>
                                <td><?= e(ucfirst($tpl->progression_mode)) ?></td>
                                <td><?= (int) ($tpl->stage_count ?? 0) ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline" href="<?= e(base_url('pipeline/templates/' . $tpl->id)) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="wf-card">
            <div class="wf-card-head"><h3>Active runs</h3></div>
            <?php if (empty($runs)): ?>
                <?php \App\Core\View::component('empty-state', [
                    'title' => 'No active workflows',
                    'message' => 'Start a template from a project’s Workflow tab.',
                ]); ?>
            <?php else: ?>
                <ul class="wf-dash-list">
                    <?php foreach ($runs as $run): ?>
                        <li>
                            <a href="<?= e(base_url('pipeline/runs/' . $run->id)) ?>">
                                <span>
                                    <strong><?= e($run->name) ?></strong>
                                    <small><?= e($run->project_name ?: '') ?> · <?= e($run->client_name ?: '') ?>
                                        <?php if ($run->current_stage_name): ?> · <?= e($run->current_stage_name) ?><?php endif; ?>
                                    </small>
                                </span>
                                <?php \App\Core\View::component('status-badge', ['status' => $run->status]); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
