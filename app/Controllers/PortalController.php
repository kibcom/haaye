<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Deliverable;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Project;
use App\Services\ApprovalService;

final class PortalController extends Controller
{
    public function company(): void
    {
        $clientId = $this->clientId();
        $client = $clientId > 0 ? Client::findInOrganization($clientId, $this->tenantId()) : null;
        $this->view('portal/company', [
            'title' => 'Company',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Company'],
            ],
            'client' => $client,
            'contacts' => $client instanceof Client ? ClientContact::forClient((int) $client->id, (int) $client->organization_id) : [],
        ]);
    }

    public function projects(): void
    {
        $clientId = $this->clientId();
        $this->view('portal/projects', [
            'title' => 'Projects',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects'],
            ],
            'projects' => $clientId > 0 ? Project::forClient($clientId, $this->tenantId()) : [],
        ]);
    }

    public function project(string $id): void
    {
        $project = $this->projectForClient($id);
        $orgId = (int) $project->organization_id;
        $deliverables = array_values(array_filter(
            Deliverable::forClientPortal($orgId, (int) $project->client_id, false, false, 50),
            static function (Deliverable $item) use ($project): bool {
                return (int) $item->project_id === (int) $project->id;
            }
        ));

        $this->view('portal/project', [
            'title' => $project->name,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Projects', 'url' => 'portal/projects'],
                ['label' => $project->name],
            ],
            'project' => $project,
            'milestones' => Milestone::forProject((int) $project->id, $orgId),
            'deliverables' => $deliverables,
        ]);
    }

    public function invoices(): void
    {
        $clientId = $this->clientId();
        $this->view('portal/invoices', [
            'title' => 'Invoices',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Invoices'],
            ],
            'invoices' => $clientId > 0 ? Invoice::forClientAccount($this->tenantId(), $clientId) : [],
            'currency' => $this->currentOrganization()?->currency ?? 'USD',
        ]);
    }

    public function invoice(string $id): void
    {
        $invoice = $this->invoiceForClient($id);
        $this->view('portal/invoice', [
            'title' => $invoice->number,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Invoices', 'url' => 'portal/invoices'],
                ['label' => $invoice->number],
            ],
            'invoice' => $invoice,
            'items' => InvoiceItem::forInvoice((int) $invoice->id, (int) $invoice->organization_id),
            'payments' => Payment::forInvoice((int) $invoice->id, (int) $invoice->organization_id),
        ]);
    }

    public function payments(): void
    {
        $clientId = $this->clientId();
        $this->view('portal/payments', [
            'title' => 'Payments',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Payments'],
            ],
            'payments' => $clientId > 0 ? Payment::forClientAccount($this->tenantId(), $clientId) : [],
        ]);
    }

    public function files(): void
    {
        $clientId = $this->clientId();
        $this->view('portal/files', [
            'title' => 'Files',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Files'],
            ],
            'files' => $clientId > 0 ? Deliverable::forClientPortal($this->tenantId(), $clientId, true, false, 50) : [],
        ]);
    }

    public function notifications(): void
    {
        $this->clientId();
        $userId = (int) Auth::id();
        $this->view('portal/notifications', [
            'title' => 'Notifications',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => 'dashboard'],
                ['label' => 'Notifications'],
            ],
            'notifications' => Notification::forUser($this->tenantId(), $userId),
        ]);
    }

    public function openNotification(string $id): void
    {
        $this->clientId();
        $userId = (int) Auth::id();
        $notification = Notification::findForUser((int) $id, $this->tenantId(), $userId);
        if (!$notification instanceof Notification) {
            Response::notFound();
        }
        Notification::markRead((int) $notification->id, $this->tenantId(), $userId);
        $path = $notification->safePath();
        $this->redirect($path ?? 'portal/notifications');
    }

    public function readAll(): void
    {
        $this->clientId();
        Notification::markAllRead($this->tenantId(), (int) Auth::id());
        flash('success', 'Notifications marked as read.');
        $this->redirect('portal/notifications');
    }

    private function clientId(): int
    {
        $this->authorize('portal.view');
        $clientId = ApprovalService::viewerClientId();
        if ($clientId === null) {
            Response::forbidden();
        }

        return (int) $clientId;
    }

    private function projectForClient(string $id): Project
    {
        $clientId = $this->clientId();
        $project = Project::findInOrganization((int) $id, $this->tenantId());
        if (!$project instanceof Project || $clientId <= 0 || (int) $project->client_id !== $clientId) {
            Response::notFound();
        }

        return $project;
    }

    private function invoiceForClient(string $id): Invoice
    {
        $clientId = $this->clientId();
        $invoice = Invoice::findInOrganization((int) $id, $this->tenantId());
        if (!$invoice instanceof Invoice || $clientId <= 0 || (int) $invoice->client_id !== $clientId || $invoice->status === 'draft') {
            Response::notFound();
        }

        return $invoice;
    }
}
