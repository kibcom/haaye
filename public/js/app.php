<?php

declare(strict_types=1);

$file = __DIR__ . '/app.dat';
if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Script not found.';
    exit;
}

$mtime = (int) filemtime($file);
header('Content-Type: application/javascript; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: "' . $mtime . '-' . filesize($file) . '"');

if (
    isset($_SERVER['HTTP_IF_NONE_MATCH'])
    && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === '"' . $mtime . '-' . filesize($file) . '"'
) {
    http_response_code(304);
    exit;
}

readfile($file);
