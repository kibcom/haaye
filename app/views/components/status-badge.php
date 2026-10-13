<?php
$map = [
    'active' => 'success',
    'inactive' => 'secondary',
    'suspended' => 'warning',
    'invited' => 'info',
    'lead' => 'info',
    'new' => 'info',
    'contacted' => 'primary',
    'qualified' => 'warning',
    'converted' => 'success',
    'lost' => 'secondary',
    'scheduled' => 'info',
    'confirmed' => 'primary',
    'archived' => 'dark',
    'planning' => 'info',
    'on_hold' => 'warning',
    'completed' => 'success',
    'cancelled' => 'secondary',
    'open' => 'info',
    'in_progress' => 'primary',
    'review' => 'primary',
    'backlog' => 'secondary',
    'todo' => 'info',
    'changes_requested' => 'warning',
    'pending' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'cancelled' => 'secondary',
    'draft' => 'secondary',
    'internal_review' => 'info',
    'client_review' => 'primary',
    'delivered' => 'success',
    'high' => 'danger',
    'medium' => 'warning',
    'low' => 'secondary',
    'urgent' => 'danger',
    'unpaid' => 'warning',
    'partial' => 'info',
    'paid' => 'success',
    'sent' => 'info',
    'overdue' => 'danger',
    'not_required' => 'secondary',
];
$tone = $map[$status ?? ''] ?? 'secondary';
$slug = (string) ($status ?? '');
$knownLabels = ['partial' => 'Partially paid'];
$label = $label ?? ($knownLabels[$slug] ?? ucwords(str_replace('_', ' ', $slug)));
?>
<?php if (!empty($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $color) === 1): ?>
    <span class="wf-badge wf-badge-custom" style="color: <?= e((string) $color) ?>; border-color: <?= e((string) $color) ?>"><?= e((string) $label) ?></span>
<?php else: ?>
    <span class="badge text-bg-<?= e($tone) ?> wf-badge"><?= e((string) $label) ?></span>
<?php endif; ?>
