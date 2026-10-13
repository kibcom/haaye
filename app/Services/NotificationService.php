<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;

final class NotificationService
{
    public static function notifyClient(?int $organizationId, ?int $clientId, string $title, ?string $body, ?string $link): void
    {
        if ($organizationId === null || $organizationId <= 0 || $clientId === null || $clientId <= 0) {
            return;
        }

        $users = Database::query(
            "SELECT u.id
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.organization_id = ? AND u.client_id = ? AND u.status = 'active' AND r.slug = 'client'",
            [$organizationId, $clientId]
        )->fetchAll();

        self::notifyMany($organizationId, array_column($users, 'id'), $title, $body, $link);
    }

    public static function notifyUser(?int $organizationId, ?int $userId, string $title, ?string $body, ?string $link, ?int $exceptUserId = null): void
    {
        if ($organizationId === null || $organizationId <= 0 || $userId === null || $userId <= 0) {
            return;
        }
        if ($exceptUserId !== null && (int) $exceptUserId === (int) $userId) {
            return;
        }

        self::notifyMany($organizationId, [$userId], $title, $body, $link);
    }

    /** @param list<int|string> $userIds */
    public static function notifyMany(?int $organizationId, array $userIds, string $title, ?string $body, ?string $link): void
    {
        if ($organizationId === null || $organizationId <= 0) {
            return;
        }

        $title = trim($title);
        if ($title === '') {
            return;
        }

        $ids = [];
        foreach ($userIds as $userId) {
            if (is_numeric($userId) && (int) $userId > 0) {
                $ids[(int) $userId] = true;
            }
        }
        if ($ids === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach (array_keys($ids) as $userId) {
            Notification::create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'title' => substr($title, 0, 180),
                'body' => $body !== null && $body !== '' ? substr($body, 0, 500) : null,
                'link_path' => $link,
                'created_at' => $now,
            ]);
        }
    }

    public static function notifyPermission(int $organizationId, string $permission, string $title, ?string $body, ?string $link, ?int $exceptUserId = null): void
    {
        self::notifyPermissions($organizationId, [$permission], $title, $body, $link, $exceptUserId);
    }

    /** @param list<string> $permissions */
    public static function notifyPermissions(int $organizationId, array $permissions, string $title, ?string $body, ?string $link, ?int $exceptUserId = null): void
    {
        $needed = array_values(array_filter($permissions, static fn ($slug): bool => is_string($slug) && $slug !== ''));
        if ($needed === []) {
            return;
        }

        $ids = [];
        foreach (self::staffUsers($organizationId) as $user) {
            if ($exceptUserId !== null && (int) $user->id === (int) $exceptUserId) {
                continue;
            }
            $have = AuthorizationService::permissionsForUser($user);
            foreach ($needed as $permission) {
                if (in_array($permission, $have, true)) {
                    $ids[] = (int) $user->id;
                    break;
                }
            }
        }
        self::notifyMany($organizationId, $ids, $title, $body, $link);
    }

    public static function remindDueToday(int $organizationId, int $userId, ?string $today = null): void
    {
        $today = $today ?: date('Y-m-d');
        $soon = date('Y-m-d', strtotime($today . ' +3 days'));
        $tasks = Task::openDueBy($organizationId, $soon, $userId);
        foreach ($tasks as $task) {
            $due = substr((string) $task->due_date, 0, 10);
            if ($due < $today) {
                $title = 'Overdue: ' . $task->title;
                $marker = 'task-overdue:' . $today . ':' . (int) $task->id;
            } elseif ($due === $today) {
                $title = 'Due today: ' . $task->title;
                $marker = 'task-due:' . $today . ':' . (int) $task->id;
            } else {
                $title = 'Due soon: ' . $task->title;
                $marker = 'task-soon:' . $today . ':' . (int) $task->id;
            }
            if (self::alreadyNotified($organizationId, $userId, $marker)) {
                continue;
            }
            self::notifyUser(
                $organizationId,
                $userId,
                $title,
                ($task->project_name ?: 'Project') . ' · due ' . format_date($due),
                'tasks/' . (int) $task->id . '#' . $marker
            );
        }
    }

    public static function remindPayments(int $organizationId, int $userId, string $today, ?int $onlyUserId = null, ?int $clientId = null, bool $portal = false): void
    {
        $soon = date('Y-m-d', strtotime($today . ' +3 days'));
        $invoices = Invoice::openDueBy($organizationId, $soon, $onlyUserId, $clientId);
        foreach ($invoices as $invoice) {
            $due = substr((string) $invoice->due_date, 0, 10);
            if ($due < $today) {
                $title = 'Payment overdue: ' . $invoice->number;
                $marker = 'pay-overdue:' . $today . ':' . (int) $invoice->id;
            } elseif ($due === $today) {
                $title = 'Payment due today: ' . $invoice->number;
                $marker = 'pay-due:' . $today . ':' . (int) $invoice->id;
            } else {
                $title = 'Payment due soon: ' . $invoice->number;
                $marker = 'pay-soon:' . $today . ':' . (int) $invoice->id;
            }
            if (self::alreadyNotified($organizationId, $userId, $marker)) {
                continue;
            }
            $path = ($portal ? 'portal/invoices/' : 'invoices/') . (int) $invoice->id;
            self::notifyUser(
                $organizationId,
                $userId,
                $title,
                ($invoice->client_name ?: 'Client') . ' · ' . money((string) $invoice->currency, $invoice->balance) . ' · due ' . format_date($due),
                $path . '#' . $marker
            );
        }
    }

    public static function alreadyNotified(int $organizationId, int $userId, string $marker): bool
    {
        $row = Database::query(
            'SELECT id FROM notifications
             WHERE organization_id = ? AND user_id = ? AND link_path LIKE ?
             AND created_at >= ?
             LIMIT 1',
            [$organizationId, $userId, '%#' . $marker, date('Y-m-d 00:00:00')]
        )->fetch();

        return $row !== false;
    }

    /** @return list<User> */
    private static function staffUsers(int $organizationId): array
    {
        $rows = Database::query(
            "SELECT u.*
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.organization_id = ? AND u.status = 'active' AND r.slug <> 'client'
             ORDER BY u.id ASC",
            [$organizationId]
        )->fetchAll();

        return array_map([User::class, 'hydrate'], $rows);
    }
}
