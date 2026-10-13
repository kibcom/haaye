<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

final class PermissionMiddleware
{
    public static function handle(string $permission): void
    {
        if (!Auth::can($permission)) {
            Response::forbidden();
        }
    }
}
