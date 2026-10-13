<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Haaye Manager'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', 'false') === 'true',
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8080'), '/'),
    'base_path' => rtrim((string) env('APP_BASE_PATH', ''), '/'),
    'key' => (string) env('APP_KEY', ''),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
];
