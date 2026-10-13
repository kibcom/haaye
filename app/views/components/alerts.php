<?php if (!empty($alert) && is_array($alert)): ?>
    <div class="alert alert-<?= e($alert['type'] ?? 'info') ?> wf-alert" role="alert">
        <?= e($alert['message'] ?? '') ?>
    </div>
<?php endif; ?>

<?php
$formErrors = errors();
if ($formErrors !== []):
    $flat = [];
    foreach ($formErrors as $messages) {
        foreach ((array) $messages as $message) {
            $flat[] = $message;
        }
    }
?>
    <div class="alert alert-danger wf-alert" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($flat as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
