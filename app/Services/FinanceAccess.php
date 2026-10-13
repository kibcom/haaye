<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;

final class FinanceAccess
{
    public static function canViewInvoice(Invoice $invoice): bool
    {
        if (!Auth::can('invoices.view') || (int) $invoice->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        if (ProjectAccess::seesAll()) {
            return true;
        }

        if ($invoice->project_id === null) {
            return false;
        }

        $project = Project::findInOrganization((int) $invoice->project_id, (int) $invoice->organization_id);

        return $project instanceof Project && ProjectAccess::canView($project);
    }

    public static function canViewExpense(Expense $expense): bool
    {
        if (!Auth::can('expenses.view') || (int) $expense->organization_id !== (int) Auth::organizationId()) {
            return false;
        }

        if (Auth::can('expenses.view_all') || Auth::can('expenses.approve')) {
            return true;
        }

        if ((int) $expense->user_id === (int) Auth::id()) {
            return true;
        }

        if (!Auth::can('invoices.view') || $expense->project_id === null) {
            return false;
        }

        $project = Project::findInOrganization((int) $expense->project_id, (int) $expense->organization_id);

        return $project instanceof Project && ProjectAccess::canView($project);
    }

    public static function canDeleteExpense(Expense $expense): bool
    {
        if (!self::canViewExpense($expense) || $expense->status !== 'pending') {
            return false;
        }

        if ((int) $expense->user_id === (int) Auth::id() && Auth::can('expenses.create')) {
            return true;
        }

        return Auth::can('expenses.approve');
    }

    public static function expenseOwnOnly(): bool
    {
        return !Auth::can('expenses.view_all') && !Auth::can('expenses.approve') && !Auth::can('invoices.view');
    }
}
