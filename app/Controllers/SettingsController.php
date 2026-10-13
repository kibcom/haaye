<?php

declare(strict_types=1);

namespace App\Controllers;

final class SettingsController extends Controller
{
    public function index(): void
    {
        if (!is_super_admin() && can('organization.view')) {
            $this->redirect('settings/organization');
        }

        $this->redirect('settings/appearance');
    }

    public function appearance(): void
    {
        $this->view('settings/appearance', [
            'title' => 'Settings',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => is_super_admin() ? 'platform' : 'dashboard'],
                ['label' => 'Settings'],
            ],
        ]);
    }
}
