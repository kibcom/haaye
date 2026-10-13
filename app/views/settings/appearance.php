<?php \App\Core\View::component('settings-nav', []); ?>
<div class="wf-page-header">
    <div>
        <p class="wf-kicker">Settings</p>
        <h2>Appearance</h2>
    </div>
</div>

<section class="wf-card wf-appearance">
    <div class="wf-appearance-row" role="radiogroup" aria-label="Theme">
        <button class="wf-appearance-option" type="button" data-theme-choice="light" aria-pressed="false"><?= icon('sun') ?> Light</button>
        <button class="wf-appearance-option" type="button" data-theme-choice="dark" aria-pressed="false"><?= icon('moon') ?> Dark</button>
        <button class="wf-appearance-option" type="button" data-theme-choice="system" aria-pressed="false"><?= icon('circle-half') ?> System</button>
    </div>
</section>
