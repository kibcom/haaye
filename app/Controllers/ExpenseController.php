<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\FileUploader;
use App\Core\Request;
use App\Core\Response;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\FinanceAccess;
use App\Services\ProjectAccess;

final class ExpenseController extends Controller
{
    public function index(): void
    {
        $this->authorize('expenses.view');
        $orgId = $this->tenantId();
        $status = (string) Request::input('status', '');
        if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) {
            $status = '';
        }

        $this->view('expenses/index', [
            'title' => 'Expenses',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Expenses'],
            ],
            'expenses' => Expense::listVisible(
                $orgId,
                ProjectAccess::restrictToUserId(),
                FinanceAccess::expenseOwnOnly(),
                (int) Auth::id(),
                $status !== '' ? $status : null
            ),
            'status' => $status,
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
        ]);
    }

    public function create(): void
    {
        $this->authorize('expenses.create');
        $this->form();
    }

    public function store(): void
    {
        $this->authorize('expenses.create');
        try {
            $expense = (new ExpenseService())->create($this->tenantId(), Request::all(), Request::file('receipt'), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('expenses/create');
        }

        $this->clearOld();
        flash('success', 'Expense submitted for approval.');
        $this->redirect('expenses/' . $expense->id);
    }

    public function show(string $id): void
    {
        $expense = $this->visible($id);
        $this->view('expenses/show', [
            'title' => 'Expense',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Expenses', 'url' => 'expenses'],
                ['label' => ucfirst($expense->category)],
            ],
            'expense' => $expense,
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
            'canDecide' => Auth::can('expenses.approve') && $expense->status === 'pending',
            'canDelete' => FinanceAccess::canDeleteExpense($expense),
        ]);
    }

    public function decide(string $id): void
    {
        $this->authorize('expenses.approve');
        $expense = $this->visible($id);
        try {
            (new ExpenseService())->decide(
                $expense,
                (string) Request::input('decision', ''),
                (string) Request::input('comment', ''),
                (int) $this->actor()->id
            );
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('expenses/' . $expense->id);
        }

        flash('success', 'Expense decision saved.');
        $this->redirect('expenses/' . $expense->id);
    }

    public function download(string $id): void
    {
        $expense = $this->visible($id);
        if ($expense->stored_path === null || $expense->stored_path === '') {
            Response::notFound();
        }
        $path = FileUploader::privateAbsolutePath($expense->stored_path);
        if ($path === null || !is_file($path)) {
            Response::notFound();
        }

        $name = str_replace(['"', "\r", "\n"], '', (string) $expense->original_name);
        if ($name === '') {
            $name = 'receipt';
        }
        header('Content-Type: ' . ($expense->mime_type ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function destroy(string $id): void
    {
        $expense = $this->visible($id);
        try {
            (new ExpenseService())->delete($expense, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('expenses/' . $expense->id);
        }

        flash('success', 'Expense deleted.');
        $this->redirect('expenses');
    }

    private function form(): void
    {
        $orgId = $this->tenantId();
        $this->view('expenses/form', [
            'title' => 'Record expense',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Expenses', 'url' => 'expenses'],
                ['label' => 'Record'],
            ],
            'projects' => Project::optionsVisible($orgId, ProjectAccess::restrictToUserId()),
            'clients' => Client::optionsForOrganization($orgId),
            'people' => Auth::can('expenses.approve') ? User::listAssignable($orgId) : [],
            'categories' => Expense::categories(),
        ]);
    }

    private function visible(string $id): Expense
    {
        $expense = Expense::findInOrganization((int) $id, $this->tenantId());
        if (!$expense instanceof Expense || !FinanceAccess::canViewExpense($expense)) {
            Response::notFound();
        }

        return $expense;
    }
}
