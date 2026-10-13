<?php
$pagination = $pagination ?? null;
if (!$pagination || (int) $pagination['last_page'] <= 1) {
    return;
}

$query = $_GET ?? [];
unset($query['page']);
$baseQuery = http_build_query($query);
$qs = $baseQuery !== '' ? '&' . $baseQuery : '';
$page = (int) $pagination['page'];
$last = (int) $pagination['last_page'];
?>
<nav class="wf-pagination" aria-label="Pagination">
    <span>Page <?= (int) $page ?> of <?= (int) $last ?></span>
    <div>
        <?php if ($page > 1): ?>
            <a class="btn btn-sm btn-outline" href="?page=<?= $page - 1 ?><?= e($qs) ?>"><?= icon('chevron-left') ?> Previous</a>
        <?php endif; ?>
        <?php if ($page < $last): ?>
            <a class="btn btn-sm btn-outline" href="?page=<?= $page + 1 ?><?= e($qs) ?>"><?= icon('chevron-right') ?> Next</a>
        <?php endif; ?>
    </div>
</nav>
