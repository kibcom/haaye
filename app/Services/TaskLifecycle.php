<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Canonical task work/approval lifecycle (separate from free-form board moves).
 */
final class TaskLifecycle
{
    public const DRAFT = 'draft';
    public const ASSIGNED = 'assigned';
    public const IN_PROGRESS = 'in_progress';
    public const SUBMITTED = 'submitted';
    public const CHANGES_REQUESTED = 'changes_requested';
    public const RESUBMITTED = 'resubmitted';
    public const APPROVED = 'approved';
    public const BLOCKED = 'blocked';
    public const CANCELLED = 'cancelled';

    /** @var list<string> */
    public const WORK_STATUSES = [
        self::DRAFT,
        self::ASSIGNED,
        self::IN_PROGRESS,
        self::SUBMITTED,
        self::CHANGES_REQUESTED,
        self::RESUBMITTED,
        self::APPROVED,
        self::BLOCKED,
        self::CANCELLED,
    ];

    /** @var list<string> */
    public const OPEN_FOR_EMPLOYEE = [
        self::ASSIGNED,
        self::IN_PROGRESS,
        self::CHANGES_REQUESTED,
        self::RESUBMITTED,
        self::BLOCKED,
        'todo',
        'backlog',
        'review',
    ];

    public static function canStart(string $status): bool
    {
        return in_array($status, [self::DRAFT, self::ASSIGNED, 'todo', 'backlog'], true);
    }

    public static function canSubmit(string $status): bool
    {
        return in_array($status, [
            self::ASSIGNED,
            self::IN_PROGRESS,
            self::CHANGES_REQUESTED,
            'todo',
            'in_progress',
            'review',
        ], true);
    }

    public static function canApprove(string $status): bool
    {
        return in_array($status, [self::SUBMITTED, self::RESUBMITTED, 'review'], true);
    }

    public static function nextAfterSubmit(string $current): string
    {
        return $current === self::CHANGES_REQUESTED ? self::RESUBMITTED : self::SUBMITTED;
    }

    public static function label(string $slug): string
    {
        return match ($slug) {
            self::DRAFT => 'Draft',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::SUBMITTED => 'Submitted for Approval',
            self::CHANGES_REQUESTED => 'Changes Requested',
            self::RESUBMITTED => 'Resubmitted',
            self::APPROVED => 'Approved',
            self::BLOCKED => 'Blocked',
            self::CANCELLED => 'Cancelled',
            default => ucwords(str_replace('_', ' ', $slug)),
        };
    }
}
