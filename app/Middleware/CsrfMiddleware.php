<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public static function handle(): void
    {
        $token = Request::input('_token');
        if (!is_string($token) || $token === '') {
            $token = Request::header('X-CSRF-TOKEN');
        }

        if (!Csrf::verify($token)) {
            Logger::warning('CSRF validation failed from ' . Request::ip() . ' on ' . Request::path());
            Response::abort(403, 'Your session has expired. Please refresh and try again.');
        }
    }
}
