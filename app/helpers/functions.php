<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
        return $value === '' ? $default : (string) $value;
    }

    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_name(): string
{
    return (string) Config::get('app.name', 'Haaye Manager');
}

function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

    // Front controller inside /public (normal or subdirectory install)
    if (preg_match('#^(.*?)/public/index\.php$#', $script, $matches)) {
        $parent = rtrim($matches[1], '/');
        // Hit via /public/... URL (root rewrite not active)
        if (
            $uri === '/public' || str_starts_with($uri, '/public/')
            || ($parent !== '' && ($uri === $parent . '/public' || str_starts_with($uri, $parent . '/public/')))
        ) {
            return $cached = ($parent === '' ? '/public' : $parent . '/public');
        }

        return $cached = $parent;
    }

    // /index.php at domain or folder root
    if (preg_match('#^(.*?)/index\.php$#', $script, $matches)) {
        $parent = rtrim($matches[1], '/');

        // Document root is the public/ folder → normal domain URLs
        if (str_ends_with($scriptFile, '/public/index.php')) {
            return $cached = $parent;
        }

        // Project-root index.php wrapper with a public/ folder (cPanel default)
        if (is_dir(BASE_PATH . '/public') && is_file(BASE_PATH . '/public/index.php')) {
            return $cached = ($parent === '' ? '/public' : $parent . '/public');
        }

        return $cached = $parent;
    }

    return $cached = rtrim((string) Config::get('app.base_path', ''), '/');
}

function base_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $basePath = app_base_path();

    if ($host !== '') {
        $root = ($https ? 'https' : 'http') . '://' . $host . $basePath;
    } else {
        $root = rtrim((string) Config::get('app.url', 'http://localhost'), '/');
    }

    $path = ltrim($path, '/');
    return $path === '' ? $root : $root . '/' . $path;
}

function public_path(string $path = ''): string
{
    static $root = null;
    if ($root === null) {
        $root = (is_dir(BASE_PATH . '/public') && is_file(BASE_PATH . '/public/index.php'))
            ? BASE_PATH . '/public'
            : BASE_PATH;
    }

    $path = ltrim($path, '/');
    return $path === '' ? $root : $root . '/' . $path;
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = public_path($path);
    $url = base_url($path);
    if (!is_file($file)) {
        return $url;
    }

    return $url . '?v=' . filemtime($file);
}

function redirect_to(string $path): void
{
    header('Location: ' . base_url(ltrim($path, '/')), true, 302);
    exit;
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old', []);
    return $old[$key] ?? $default;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function auth_user(): ?\App\Models\User
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function is_super_admin(): bool
{
    return Auth::isSuperAdmin();
}

function flash(string $type, string $message): void
{
    Session::flash('alert', ['type' => $type, 'message' => $message]);
}

function errors(): array
{
    static $bag = null;
    if ($bag !== null) {
        return $bag;
    }

    if (isset($_SESSION['_flash']['_errors'])) {
        $bag = $_SESSION['_flash']['_errors'];
        unset($_SESSION['_flash']['_errors']);
    } else {
        $bag = Session::get('_errors', []);
    }

    return is_array($bag) ? $bag : [];
}

function error(string $key): ?string
{
    $errors = errors();
    return $errors[$key][0] ?? null;
}

function has_error(string $key): bool
{
    return error($key) !== null;
}

function request_ip(): string
{
    return Request::ip();
}

function selected(mixed $current, mixed $expected): string
{
    return (string) $current === (string) $expected ? 'selected' : '';
}

function checked(mixed $current, mixed $expected): string
{
    return (string) $current === (string) $expected ? 'checked' : '';
}

function format_datetime(?string $value, string $format = 'M j, Y g:i A'): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date($format, $timestamp);
}

function format_date(?string $value): string
{
    return format_datetime($value, 'M j, Y');
}

/** @return array{label: string, tone: string}|null */
function due_reminder(?string $date, ?string $today = null): ?array
{
    $due = substr((string) $date, 0, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) !== 1) {
        return null;
    }

    $today = $today ?: date('Y-m-d');
    if ($due < $today) {
        return ['label' => 'Overdue', 'tone' => 'overdue'];
    }
    if ($due === $today) {
        return ['label' => 'Due today', 'tone' => 'today'];
    }

    $days = (int) round((strtotime($due) - strtotime($today)) / 86400);
    if ($days < 1) {
        return null;
    }

    return ['label' => 'Due in ' . $days . ($days === 1 ? ' day' : ' days'), 'tone' => 'soon'];
}

function format_minutes(int $minutes): string
{
    $minutes = max(0, $minutes);
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    if ($hours === 0) {
        return $rest . 'm';
    }
    if ($rest === 0) {
        return $hours . 'h';
    }

    return $hours . 'h ' . $rest . 'm';
}

function money(string $currency, mixed $amount): string
{
    return strtoupper($currency) . ' ' . number_format((float) $amount, 2);
}

function feature_enabled(string $slug): bool
{
    return \App\Services\FeatureService::allows($slug);
}

function guide_menu_enabled(): bool
{
    if (is_super_admin()) {
        return true;
    }

    $organization = auth_user()?->organization();

    return $organization instanceof \App\Models\Organization && (int) $organization->show_guide === 1;
}

function icon(string $name): string
{
    $safe = preg_replace('/[^a-z0-9-]/', '', $name) ?? '';

    return '<i class="bi bi-' . $safe . '" aria-hidden="true"></i>';
}

function task_status_targets(array $statuses): array
{
    $complete = null;
    $reopen = null;
    foreach ($statuses as $status) {
        $slug = (string) ($status->slug ?? '');
        $closed = (int) ($status->is_closed ?? 0) === 1;
        if ($slug === '') {
            continue;
        }
        if ($closed && $complete === null) {
            $complete = $slug;
        }
        if (!$closed && $reopen === null) {
            $reopen = $slug;
        }
        if ($slug === 'completed' && $closed) {
            $complete = $slug;
        }
        if ($slug === 'todo' && !$closed) {
            $reopen = $slug;
        }
    }

    return [$complete, $reopen];
}

function user_initials(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'U';
}
