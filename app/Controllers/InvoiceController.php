<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Services\FinanceAccess;
use App\Services\InvoiceService;
use App\Services\ProjectAccess;

final class InvoiceController extends Controller
{
    public function index(): void
    {
        $this->authorize('invoices.view');
        $status = (string) Request::input('status', '');
        $allowed = ['', 'draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $status = '';
        }

        $this->view('invoices/index', [
            'title' => 'Invoices',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Invoices'],
            ],
            'invoices' => Invoice::listForOrganization($this->tenantId(), ProjectAccess::restrictToUserId(), $status !== '' ? $status : null),
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        $this->authorize('invoices.manage');
        $this->form(null);
    }

    public function store(): void
    {
        $this->authorize('invoices.manage');
        try {
            $invoice = (new InvoiceService())->create($this->tenantId(), Request::all(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->withErrors([]);
            $this->redirect('invoices/create');
        }

        $this->clearOld();
        flash('success', 'Draft invoice ' . $invoice->number . ' has been created.');
        $this->redirect('invoices/' . $invoice->id);
    }

    public function show(string $id): void
    {
        $invoice = $this->visible($id);
        $this->view('invoices/show', [
            'title' => $invoice->number,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Invoices', 'url' => 'invoices'],
                ['label' => $invoice->number],
            ],
            'invoice' => $invoice,
            'items' => InvoiceItem::forInvoice((int) $invoice->id, (int) $invoice->organization_id),
            'payments' => Payment::forInvoice((int) $invoice->id, (int) $invoice->organization_id),
            'canManage' => Auth::can('invoices.manage'),
            'canPay' => Auth::can('payments.manage') && !in_array($invoice->status, ['draft', 'cancelled', 'paid'], true) && (float) $invoice->balance > 0,
        ]);
    }

    public function edit(string $id): void
    {
        $this->authorize('invoices.manage');
        $invoice = $this->visible($id);
        if ($invoice->status !== 'draft') {
            flash('danger', 'Only a draft invoice can be edited.');
            $this->redirect('invoices/' . $invoice->id);
        }
        $this->form($invoice);
    }

    public function update(string $id): void
    {
        $this->authorize('invoices.manage');
        $invoice = $this->visible($id);
        try {
            (new InvoiceService())->update($invoice, Request::all(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('invoices/' . $invoice->id . '/edit');
        }

        $this->clearOld();
        flash('success', 'Invoice updated.');
        $this->redirect('invoices/' . $invoice->id);
    }

    public function send(string $id): void
    {
        $this->authorize('invoices.manage');
        $invoice = $this->visible($id);
        try {
            (new InvoiceService())->send($invoice, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('invoices/' . $invoice->id);
        }

        flash('success', 'Invoice marked as sent.');
        $this->redirect('invoices/' . $invoice->id);
    }

    public function cancel(string $id): void
    {
        $this->authorize('invoices.manage');
        $invoice = $this->visible($id);
        try {
            (new InvoiceService())->cancel($invoice, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('invoices/' . $invoice->id);
        }

        flash('success', 'Invoice cancelled.');
        $this->redirect('invoices/' . $invoice->id);
    }

    public function pay(string $id): void
    {
        $this->authorize('payments.manage');
        $invoice = $this->visible($id);
        try {
            (new InvoiceService())->recordPayment($invoice, Request::all(), (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('invoices/' . $invoice->id);
        }

        flash('success', 'Payment recorded. The balance has been updated.');
        $this->redirect('invoices/' . $invoice->id);
    }

    public function destroyPayment(string $id): void
    {
        $this->authorize('payments.manage');
        $payment = Payment::findInOrganization((int) $id, $this->tenantId());
        if (!$payment instanceof Payment) {
            Response::notFound();
        }
        $invoice = Invoice::findInOrganization((int) $payment->invoice_id, $this->tenantId());
        if (!$invoice instanceof Invoice || !FinanceAccess::canViewInvoice($invoice)) {
            Response::notFound();
        }

        try {
            (new InvoiceService())->deletePayment($payment, (int) $this->actor()->id);
        } catch (\RuntimeException $e) {
            flash('danger', $e->getMessage());
            $this->redirect('invoices/' . $invoice->id);
        }

        flash('success', 'Payment removed. The balance has been updated.');
        $this->redirect('invoices/' . $invoice->id);
    }

    private function form(?Invoice $invoice): void
    {
        $orgId = $this->tenantId();
        $projects = Project::optionsVisible($orgId, ProjectAccess::restrictToUserId());
        $currency = $this->currentOrganization()?->currency ?? 'USD';
        $projectIds = array_map(static fn ($project): int => (int) $project->id, $projects);
        $this->view('invoices/form', [
            'title' => $invoice ? 'Edit ' . $invoice->number : 'New invoice',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Invoices', 'url' => 'invoices'],
                ['label' => $invoice ? 'Edit' : 'New'],
            ],
            'invoice' => $invoice,
            'items' => $invoice ? InvoiceItem::forInvoice((int) $invoice->id, $orgId) : [],
            'clients' => Client::optionsForOrganization($orgId),
            'projects' => $projects,
            'currency' => $currency,
            'defaultTaxRate' => InvoiceService::defaultTaxRate($orgId),
            'paymentSnapshot' => Invoice::paymentSnapshot($orgId, $projectIds, $currency),
        ]);
    }

    private function visible(string $id): Invoice
    {
        $invoice = Invoice::findInOrganization((int) $id, $this->tenantId());
        if (!$invoice instanceof Invoice || !FinanceAccess::canViewInvoice($invoice)) {
            Response::notFound();
        }

        return $invoice;
    }
}
