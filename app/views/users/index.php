<?php
$demoPassword = $demoPassword ?? \App\Services\Phase14DemoSetup::DEMO_PASSWORD;
$positionTone = static function (?string $slug): string {
    return match ((string) $slug) {
        'owner', 'admin' => 'is-owner',
        'general_manager' => 'is-ceo',
        'operations_manager', 'manager', 'project_manager' => 'is-ops',
        'sales' => 'is-sales',
        'site_locator' => 'is-locator',
        'content_creator' => 'is-content',
        'videographer' => 'is-video',
        'editor' => 'is-editor',
        'social_media_manager' => 'is-social',
        'finance' => 'is-finance',
        'client' => 'is-client',
        default => 'is-default',
    };
};
?>
<style>
.wf-people-card { padding: 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); transition: border-color .15s ease, box-shadow .15s ease; }
.wf-people-card:hover { border-color: color-mix(in srgb, var(--primary, #0d9488) 45%, var(--border)); box-shadow: 0 6px 18px rgba(15,23,42,.06); }
.wf-people-name { display: block; color: var(--text); font-size: 1.05rem; line-height: 1.25; }
.wf-position-chip {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 8px; padding: 4px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 700; letter-spacing: .01em; line-height: 1.3;
    border: 1px solid transparent;
}
.wf-position-chip small { font-weight: 600; opacity: .85; }
.wf-position-chip.is-owner { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.wf-position-chip.is-ceo { background: #fff7ed; color: #9a3412; border-color: #fdba74; }
.wf-position-chip.is-ops { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.wf-position-chip.is-sales { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
.wf-position-chip.is-locator { background: #fefce8; color: #854d0e; border-color: #fde68a; }
.wf-position-chip.is-content { background: #faf5ff; color: #6b21a8; border-color: #e9d5ff; }
.wf-position-chip.is-video { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }
.wf-position-chip.is-editor { background: #fdf2f8; color: #9d174d; border-color: #fbcfe8; }
.wf-position-chip.is-social { background: #ecfeff; color: #0e7490; border-color: #a5f3fc; }
.wf-position-chip.is-finance { background: #f8fafc; color: #334155; border-color: #cbd5e1; }
.wf-position-chip.is-client { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.wf-position-chip.is-default { background: var(--surface-hover, #f8fafc); color: var(--text); border-color: var(--border); }
</style>

<div class="wf-page-header">
    <div>
        <p class="wf-kicker">People</p>
        <h2>Organization members</h2>
        <p class="text-muted mb-0">Manage employees and their access. Positions are highlighted on each card.</p>
    </div>
    <?php if (can('users.create')): ?>
        <a class="btn btn-aqua" href="<?= e(base_url('users/create')) ?>"><?= icon('person-plus') ?> Add person</a>
    <?php endif; ?>
</div>

<form class="wf-filters" method="get">
    <input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search name, email, or ID">
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <option value="active" <?= selected($status ?? '', 'active') ?>>Active</option>
        <option value="inactive" <?= selected($status ?? '', 'inactive') ?>>Inactive</option>
    </select>
</form>

<section class="wf-card">
    <?php if (empty($users)): ?>
        <?php \App\Core\View::component('empty-state', [
            'title' => 'No people found',
            'message' => 'Try another filter or add the first person to this organization.',
            'actionUrl' => can('users.create') ? 'users/create' : null,
            'actionLabel' => can('users.create') ? 'Add person' : null,
        ]); ?>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($users as $person): ?>
                <?php
                $isDemo = \App\Services\Phase14DemoSetup::isDemoUser((string) $person->email);
                $roleSlug = (string) ($person->role_slug ?? '');
                $positionLabel = trim((string) ($person->job_title ?: ($person->role_name ?? 'Team member')));
                $roleLabel = trim((string) ($person->role_name ?? ''));
                ?>
                <div class="col-md-6 col-xl-4">
                    <a class="wf-people-card d-block h-100 text-decoration-none" href="<?= e(base_url('users/' . $person->id)) ?>">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="wf-avatar-lg wf-avatar-fallback" style="width:48px;height:48px;font-size:14px"><?= e(user_initials($person->name)) ?></div>
                            <div class="flex-grow-1">
                                <strong class="wf-people-name"><?= e($person->name) ?></strong>
                                <span class="wf-position-chip <?= e($positionTone($roleSlug)) ?>">
                                    <?= icon('briefcase') ?>
                                    <span><?= e($positionLabel) ?></span>
                                    <?php if ($roleLabel !== '' && strcasecmp($roleLabel, $positionLabel) !== 0): ?>
                                        <small>· <?= e($roleLabel) ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="text-muted small d-block mt-2"><?= e($person->email) ?></span>
                                <?php if ($isDemo): ?>
                                    <div class="mt-2 small" style="color:var(--text)">
                                        <span class="text-muted">Demo login</span><br>
                                        Password: <code><?= e($demoPassword) ?></code>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2"><?php \App\Core\View::component('status-badge', ['status' => $person->status]); ?></div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3"><?php \App\Core\View::component('pagination', ['pagination' => $pagination]); ?></div>
    <?php endif; ?>
</section>
