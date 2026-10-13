<?php
$percent = max(0, min(100, (int) ($percent ?? 0)));
$caption = trim((string) ($caption ?? ''));
$tone = $percent >= 100 ? 'is-done' : ($percent <= 0 ? 'is-empty' : '');
$label = $percent . '% complete' . ($caption !== '' ? ', ' . $caption : '');
?>
<div class="wf-meter <?= e($tone) ?>" data-progress="<?= $percent ?>" role="img" aria-label="<?= e($label) ?>">
    <div class="wf-meter-track" aria-hidden="true"><span style="width: <?= $percent ?>%"></span></div>
    <div class="wf-meter-copy">
        <strong class="wf-meter-value"><?= $percent ?>%</strong>
        <?php if ($caption !== ''): ?><small><?= e($caption) ?></small><?php endif; ?>
    </div>
</div>
