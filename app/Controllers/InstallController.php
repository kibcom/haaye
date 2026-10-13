<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Validator;
use App\Services\InstallerService;

final class InstallController extends Controller
{
    public function show(): void
    {
        if (InstallerService::isInstalled()) {
            $this->redirect('login');
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = '';
        if (str_contains($script, '/public/')) {
            $base = substr($script, 0, (int) strpos($script, '/public/'));
        } elseif (str_ends_with($script, '/index.php')) {
            $base = substr($script, 0, -strlen('/index.php'));
        }
        $detectedUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080') . $base;

        $this->view('install/index', [
            'title' => 'Install ' . app_name(),
            'detectedUrl' => $detectedUrl,
        ], 'layouts/install');
    }

    public function store(): void
    {
        if (InstallerService::isInstalled()) {
            $this->redirect('login');
        }

        $data = Request::only([
            'app_url', 'app_timezone', 'db_host', 'db_port', 'db_database',
            'db_username', 'db_password', 'admin_name', 'admin_email',
            'admin_password', 'admin_password_confirmation',
        ]);
        $data['with_demo'] = Request::input('with_demo');

        $validator = new Validator($data);
        if (!$validator->validate([
            'app_url' => 'required',
            'db_host' => 'required',
            'db_port' => 'required',
            'db_database' => 'required',
            'db_username' => 'required',
            'admin_name' => 'required|max:160',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:10|confirmed',
        ])) {
            $this->withErrors($validator->errors());
            $this->redirect('install');
        }

        try {
            $result = (new InstallerService())->install($data, Request::input('with_demo') === '1');
        } catch (\Throwable $e) {
            $this->withErrors(['install' => ['Installation failed. Check database credentials and try again.']]);
            \App\Core\Logger::error('Install failed: ' . $e->getMessage());
            $this->redirect('install');
        }

        $this->view('install/complete', [
            'title' => 'Installation complete',
            'result' => $result,
            'adminEmail' => $data['admin_email'],
        ], 'layouts/install');
    }
}
