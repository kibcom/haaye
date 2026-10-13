<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function open(string $id): void
    {
        if (!ctype_digit($id)) {
            Response::notFound();
        }

        $userId = (int) Auth::id();
        $orgId = $this->tenantId();
        $notification = Notification::findForUser((int) $id, $orgId, $userId);
        if (!$notification instanceof Notification) {
            Response::notFound();
        }

        Notification::markRead((int) $notification->id, $orgId, $userId);
        $path = $notification->safePath();
        $this->redirect($path !== null && $path !== '' ? $path : 'dashboard');
    }

    public function readAll(): void
    {
        Notification::markAllRead($this->tenantId(), (int) Auth::id());
        flash('success', 'All notifications were marked as read.');
        $this->back();
    }
}
