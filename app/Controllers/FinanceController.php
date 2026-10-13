<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceAccess;
use App\Services\FinanceReport;
use App\Services\ProjectAccess;

final class FinanceController extends Controller
{
    public function index(): void
    {
        $this->authorize('finance.view');
        $orgId = $this->tenantId();
        $onlyUserId = ProjectAccess::restrictToUserId();
        $ownOnly = FinanceAccess::expenseOwnOnly();
        $from = date('Y-m-01', strtotime('-5 months'));

        $this->view('finance/index', [
            'title' => 'Finance',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Finance'],
            ],
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
            'metrics' => Invoice::metrics($orgId, $onlyUserId),
            'expenses' => Expense::approvedTotal($orgId, null, $onlyUserId, $ownOnly, (int) Auth::id()),
            'trend' => Payment::monthlyTotals($orgId, $onlyUserId, $from),
            'reports' => FinanceReport::build($orgId, $onlyUserId),
        ]);
    }
}
