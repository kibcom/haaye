<?php
$current = \App\Core\Request::path();
$links = [];
if (!is_super_admin() && can('organization.view')) {
    $links[] = ['settings/organization', 'Organization'];
}
$links[] = ['settings/appearance', 'Appearance'];
if (!is_super_admin() && can('roles.view')) {
    $links[] = ['roles', 'Roles'];
}
if (is_super_admin()) {
    $links[] = ['platform/guide', 'How it works'];
} elseif (in_array(auth_user()?->role()?->slug, ['owner', 'admin'], true)) {
    $links[] = ['guide', 'How it works'];
}

$active = static function (string $path) use ($current): bool {
    $full = '/' . $path;

    return $current === $full || str_starts_with($current, $full . '/');
};
?>
<div class="wf-view-toggle btn-group mb-3">
    <?php foreach ($links as $link): ?>
        <a class="btn btn-sm <?= $active($link[0]) ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(base_url($link[0])) ?>"><?= e($link[1]) ?></a>
    <?php endforeach; ?>
</div>
