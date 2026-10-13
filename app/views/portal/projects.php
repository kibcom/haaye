<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Portal</p>
        <h2>Projects</h2>
        <p class="text-muted mb-0">Projects for your company.</p>
    </div>
</div>
<section class="wf-card">
    <?php if ($projects === []): ?>
        <?php $title = 'No projects yet'; $message = 'Projects shared with your company will appear here.'; include BASE_PATH . '/app/views/components/empty-state.php'; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table wf-table align-middle mb-0">
                <thead>
                    <tr><th>Project</th><th>Status</th><th>Progress</th><th>Due</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><a href="<?= e(base_url('portal/projects/' . $project->id)) ?>"><?= e($project->name) ?></a></td>
                            <td><?php \App\Core\View::component('status-badge', ['status' => $project->status, 'label' => $project->status_name, 'color' => $project->status_color]); ?></td>
                            <td><?php \App\Core\View::component('progress-meter', ['percent' => $project->taskPercent(), 'caption' => $project->taskCaption()]); ?></td>
                            <td><?= e(format_date($project->due_date)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
