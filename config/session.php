<?php

declare(strict_types=1);

return [
    'name' => 'wfp_session',
    'lifetime' => 7200,
    'secure' => env('SESSION_SECURE', 'false') === 'true',
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
];
