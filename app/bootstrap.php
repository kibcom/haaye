<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\InstallerService;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/helpers/functions.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 0');

$debug = (bool) Config::get('app.debug', false);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($debug) {
        echo '<pre>' . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
        exit;
    }

    if (class_exists(View::class) && is_file(BASE_PATH . '/app/views/errors/500.php')) {
        View::render('errors/500', [
            'title' => '500',
            'message' => 'Something went wrong. Please try again.',
        ], 'layouts/auth');
        exit;
    }

    echo 'Something went wrong. Please try again.';
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!is_dir(BASE_PATH . '/storage/sessions')) {
    mkdir(BASE_PATH . '/storage/sessions', 0755, true);
}

Session::start();
Auth::reset();

$path = Request::path();
$installed = InstallerService::isInstalled();

if (!$installed && !str_starts_with($path, '/install')) {
    redirect_to('install');
}

if ($installed && str_starts_with($path, '/install')) {
    redirect_to('login');
}

$router = require BASE_PATH . '/routes/web.php';
$router->dispatch();
