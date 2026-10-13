<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path): void
    {
        redirect_to($path);
    }

    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if (is_string($referer) && $referer !== '') {
            header('Location: ' . $referer, true, 302);
            exit;
        }

        redirect_to('/');
    }

    public static function abort(int $code, string $message = ''): void
    {
        http_response_code($code);

        $view = match ($code) {
            403 => 'errors/403',
            404 => 'errors/404',
            default => 'errors/500',
        };

        View::render($view, [
            'title' => (string) $code,
            'message' => $message,
        ], 'layouts/auth');
        exit;
    }

    public static function forbidden(): void
    {
        Logger::warning('Permission denied for user ' . (string) (Auth::id() ?? 'guest') . ' on ' . Request::path());
        self::abort(403, 'You do not have permission to access this page.');
    }

    public static function notFound(): void
    {
        self::abort(404, 'The page you requested could not be found.');
    }

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
