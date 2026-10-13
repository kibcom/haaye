<h1>Installation complete</h1>
<p class="wf-lead">Phase 1 foundation is ready. Sign in with the Super Admin account you created.</p>

<div class="wf-complete-box">
    <p><strong>Super Admin email:</strong> <?= e($adminEmail ?? '') ?></p>
    <?php if (!empty($result['demo'])): ?>
        <p class="mb-1"><strong>Demo organization:</strong> <?= e($result['demo']['organization']) ?></p>
        <p class="mb-1"><strong>Demo password:</strong> <?= e($result['demo']['password']) ?></p>
        <ul>
            <?php foreach ($result['demo']['users'] as $email): ?>
                <li><?= e($email) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<a class="btn btn-primary w-100" href="<?= e(base_url('login')) ?>"><?= icon('box-arrow-in-right') ?> Go to sign in</a>
