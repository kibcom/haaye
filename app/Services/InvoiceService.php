<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;

final class InvoiceService
{
    /** @var list<string> */
    public const METHODS = ['bank', 'card', 'cash', 'transfer', 'other'];

    public function create(int $organizationId, array $input, int $actorId): Invoice
    {
        $payload = $this->payload($organizationId, $input);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $number = $this->allocateNumber($organizationId);
            $now = date('Y-m-d H:i:s');
            $id = Invoice::create([
                'organization_id' => $organizationId,
                'client_id' => $payload['client_id'],
                'project_id' => $payload['project_id'],
                'number' => $number,
                'issue_date' => $payload['issue_date'],
                'due_date' => $payload['due_date'],
                'currency' => $payload['currency'],
                'subtotal' => $payload['subtotal'],
                'tax_rate' => $payload['tax_rate'],
                'tax_amount' => $payload['tax_amount'],
                'discount_amount' => $payload['discount_amount'],
                'total' => $payload['total'],
                'paid_amount' => '0.00',
                'balance' => $payload['total'],
                'status' => 'draft',
                'notes' => $payload['notes'],
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            InvoiceItem::replaceForInvoice($organizationId, $id, $payload['lines']);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('The invoice could not be saved.');
        }

        $this->rememberTax($organizationId, $payload['tax_rate']);
        ActivityLog::record($organizationId, $actorId, 'invoice.created', 'Created invoice ' . $number . '.', 'invoice', $id);
        $invoice = Invoice::findInOrganization($id, $organizationId);
        if (!$invoice instanceof Invoice) {
            throw new \RuntimeException('The invoice could not be saved.');
        }

        return $invoice;
    }

    public function update(Invoice $invoice, array $input, int $actorId): void
    {
        if ($invoice->status !== 'draft') {
            throw new \RuntimeException('Only a draft invoice can be edited.');
        }

        $payload = $this->payload((int) $invoice->organization_id, $input);
        Invoice::updateById((int) $invoice->id, (int) $invoice->organization_id, [
            'client_id' => $payload['client_id'],
            'project_id' => $payload['project_id'],
            'issue_date' => $payload['issue_date'],
            'due_date' => $payload['due_date'],
            'subtotal' => $payload['subtotal'],
            'tax_rate' => $payload['tax_rate'],
            'tax_amount' => $payload['tax_amount'],
            'discount_amount' => $payload['discount_amount'],
            'total' => $payload['total'],
            'balance' => $payload['total'],
            'notes' => $payload['notes'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        InvoiceItem::replaceForInvoice((int) $invoice->organization_id, (int) $invoice->id, $payload['lines']);
        $this->rememberTax((int) $invoice->organization_id, $payload['tax_rate']);
        ActivityLog::record((int) $invoice->organization_id, $actorId, 'invoice.updated', 'Updated invoice ' . $invoice->number . '.', 'invoice', (int) $invoice->id);
    }

    public function send(Invoice $invoice, int $actorId): void
    {
        if ($invoice->status !== 'draft') {
            throw new \RuntimeException('This invoice has already been sent.');
        }
        if ((float) $invoice->total <= 0) {
            throw new \RuntimeException('Add a total greater than zero before sending.');
        }

        Invoice::updateById((int) $invoice->id, (int) $invoice->organization_id, [
            'status' => 'sent',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $invoice->organization_id, $actorId, 'invoice.sent', 'Sent invoice ' . $invoice->number . '.', 'invoice', (int) $invoice->id);
        NotificationService::notifyClient(
            (int) $invoice->organization_id,
            (int) $invoice->client_id,
            'Invoice ' . $invoice->number . ' is ready',
            'Review the balance and due date in your portal.',
            'portal/invoices/' . (int) $invoice->id
        );
    }

    public function cancel(Invoice $invoice, int $actorId): void
    {
        if ($invoice->status === 'cancelled') {
            throw new \RuntimeException('This invoice is already cancelled.');
        }
        if ($invoice->status === 'paid' || (float) $invoice->paid_amount > 0) {
            throw new \RuntimeException('Remove the payments before cancelling this invoice.');
        }

        Invoice::updateById((int) $invoice->id, (int) $invoice->organization_id, [
            'status' => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        ActivityLog::record((int) $invoice->organization_id, $actorId, 'invoice.cancelled', 'Cancelled invoice ' . $invoice->number . '.', 'invoice', (int) $invoice->id);
    }

    public function recordPayment(Invoice $invoice, array $input, int $actorId): void
    {
        if (!Auth::can('payments.manage')) {
            throw new \RuntimeException('You cannot record payments.');
        }
        if (in_array($invoice->status, ['draft', 'cancelled', 'paid'], true)) {
            throw new \RuntimeException('Payments can be recorded on a sent invoice with a balance.');
        }

        $amount = $this->money((string) ($input['amount'] ?? ''));
        if ($this->cents($amount) <= 0) {
            throw new \RuntimeException('Enter a payment amount greater than zero.');
        }
        if ($this->cents($amount) > $this->cents($invoice->balance)) {
            throw new \RuntimeException('That payment is larger than the invoice balance.');
        }

        $method = (string) ($input['method'] ?? '');
        if (!in_array($method, self::METHODS, true)) {
            throw new \RuntimeException('Choose a payment method.');
        }
        $paidOn = $this->date((string) ($input['paid_on'] ?? ''), 'Enter the payment date.');
        $reference = trim((string) ($input['reference'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        if (strlen($reference) > 80 || strlen($notes) > 2000) {
            throw new \RuntimeException('The reference or note is too long.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $locked = Database::query(
                'SELECT balance, status FROM invoices WHERE id = ? AND organization_id = ? FOR UPDATE',
                [(int) $invoice->id, (int) $invoice->organization_id]
            )->fetch();
            if (!$locked || $this->cents($amount) > $this->cents((string) $locked['balance'])) {
                throw new \RuntimeException('That payment is larger than the invoice balance.');
            }

            Payment::create([
                'organization_id' => (int) $invoice->organization_id,
                'invoice_id' => (int) $invoice->id,
                'client_id' => (int) $invoice->client_id,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference !== '' ? $reference : null,
                'notes' => $notes !== '' ? $notes : null,
                'recorded_by' => $actorId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->recalculate((int) $invoice->id, (int) $invoice->organization_id);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('The payment could not be saved.');
        }

        ActivityLog::record((int) $invoice->organization_id, $actorId, 'payment.recorded', 'Recorded ' . $invoice->currency . ' ' . $amount . ' on ' . $invoice->number . '.', 'invoice', (int) $invoice->id);
        NotificationService::notifyClient(
            (int) $invoice->organization_id,
            (int) $invoice->client_id,
            'A payment was recorded',
            $invoice->currency . ' ' . $amount . ' was applied to ' . $invoice->number . '.',
            'portal/invoices/' . (int) $invoice->id
        );
        NotificationService::notifyPermissions(
            (int) $invoice->organization_id,
            ['payments.manage', 'invoices.view', 'finance.view'],
            'Payment on ' . $invoice->number,
            strtoupper((string) $invoice->currency) . ' ' . $amount . ' recorded for ' . ($invoice->client_name ?: 'client') . '.',
            'invoices/' . (int) $invoice->id,
            $actorId
        );
    }

    public function deletePayment(Payment $payment, int $actorId): void
    {
        $invoice = Invoice::findInOrganization((int) $payment->invoice_id, (int) $payment->organization_id);
        if (!$invoice instanceof Invoice) {
            throw new \RuntimeException('That invoice could not be found.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::query(
                'SELECT id FROM invoices WHERE id = ? AND organization_id = ? FOR UPDATE',
                [(int) $invoice->id, (int) $invoice->organization_id]
            )->fetch();
            Payment::deleteById((int) $payment->id, (int) $payment->organization_id);
            $this->recalculate((int) $invoice->id, (int) $invoice->organization_id);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException('The payment could not be removed.');
        }

        ActivityLog::record((int) $invoice->organization_id, $actorId, 'payment.deleted', 'Removed a payment from ' . $invoice->number . '.', 'invoice', (int) $invoice->id);
    }

    public function allocateNumber(int $organizationId): string
    {
        $row = Database::query(
            'SELECT invoice_prefix, invoice_next_number FROM organizations WHERE id = ? FOR UPDATE',
            [$organizationId]
        )->fetch();
        if (!$row) {
            throw new \RuntimeException('The organization could not be found.');
        }

        $prefix = preg_replace('/[^A-Za-z0-9-]/', '', (string) $row['invoice_prefix']) ?: 'INV';
        $next = max(1, (int) $row['invoice_next_number']);
        $number = $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        $taken = Database::query(
            'SELECT id FROM invoices WHERE organization_id = ? AND number = ? LIMIT 1',
            [$organizationId, $number]
        )->fetch();
        if ($taken) {
            throw new \RuntimeException('That invoice number is already used. Increase the next number in organization settings.');
        }

        Database::query('UPDATE organizations SET invoice_next_number = ? WHERE id = ?', [$next + 1, $organizationId]);

        return $number;
    }

    private function recalculate(int $invoiceId, int $organizationId): void
    {
        $row = Database::query(
            'SELECT total, due_date, status FROM invoices WHERE id = ? AND organization_id = ? LIMIT 1',
            [$invoiceId, $organizationId]
        )->fetch();
        if (!$row || $row['status'] === 'cancelled') {
            return;
        }

        $paid = Payment::sumForInvoice($invoiceId, $organizationId);
        $totalCents = $this->cents((string) $row['total']);
        $paidCents = $this->cents($paid);
        if ($paidCents > $totalCents) {
            throw new \RuntimeException('Payments cannot exceed the invoice total.');
        }

        $balance = number_format(($totalCents - $paidCents) / 100, 2, '.', '');
        if ($paidCents === 0) {
            $status = 'sent';
        } elseif ($paidCents === $totalCents) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        Invoice::updateById($invoiceId, $organizationId, [
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(int $organizationId, array $input): array
    {
        $client = Client::findInOrganization((int) ($input['client_id'] ?? 0), $organizationId);
        if (!$client instanceof Client || $client->status === 'archived') {
            throw new \RuntimeException('Choose a client.');
        }

        $projectId = (int) ($input['project_id'] ?? 0);
        $project = null;
        if ($projectId > 0) {
            $project = Project::findInOrganization($projectId, $organizationId);
            if (!$project instanceof Project || !ProjectAccess::canView($project) || (int) $project->client_id !== (int) $client->id) {
                throw new \RuntimeException('Choose a project for that client.');
            }
        }

        $issue = $this->date((string) ($input['issue_date'] ?? ''), 'Enter the issue date.');
        $due = $this->date((string) ($input['due_date'] ?? ''), 'Enter the due date.');
        if ($due < $issue) {
            throw new \RuntimeException('The due date cannot be earlier than the issue date.');
        }

        $lines = $this->lines($input);
        $subtotalCents = 0;
        foreach ($lines as $line) {
            $subtotalCents += $this->cents($line['line_total']);
        }
        $taxRate = $this->rate((string) ($input['tax_rate'] ?? '0'));
        $taxCents = (int) round($subtotalCents * ($this->cents($taxRate) / 10000));
        $discount = $this->money((string) ($input['discount_amount'] ?? '0'));
        $discountCents = $this->cents($discount);
        $totalCents = $subtotalCents + $taxCents - $discountCents;
        if ($totalCents < 0) {
            throw new \RuntimeException('The discount cannot be larger than the subtotal plus tax.');
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if (strlen($notes) > 2000) {
            throw new \RuntimeException('Notes may not be longer than 2000 characters.');
        }

        $org = Database::query('SELECT currency FROM organizations WHERE id = ? LIMIT 1', [$organizationId])->fetch();

        return [
            'client_id' => (int) $client->id,
            'project_id' => $project ? (int) $project->id : null,
            'issue_date' => $issue,
            'due_date' => $due,
            'currency' => strtoupper(substr((string) ($org['currency'] ?? 'USD'), 0, 3)),
            'lines' => $lines,
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            'tax_rate' => $taxRate,
            'tax_amount' => number_format($taxCents / 100, 2, '.', ''),
            'discount_amount' => $discount,
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    /** @return list<array{description: string, quantity: string, unit_price: string, line_total: string}> */
    private function lines(array $input): array
    {
        $descriptions = $input['descriptions'] ?? [];
        $quantities = $input['quantities'] ?? [];
        $prices = $input['prices'] ?? [];
        if (!is_array($descriptions) || !is_array($quantities) || !is_array($prices)) {
            throw new \RuntimeException('Add at least one invoice line.');
        }

        $lines = [];
        $count = max(count($descriptions), count($quantities), count($prices));
        if ($count > 30) {
            throw new \RuntimeException('An invoice can have at most 30 lines.');
        }
        for ($i = 0; $i < $count; $i++) {
            $description = trim((string) ($descriptions[$i] ?? ''));
            $quantityRaw = trim((string) ($quantities[$i] ?? ''));
            $priceRaw = trim((string) ($prices[$i] ?? ''));
            if ($description === '' && $quantityRaw === '' && $priceRaw === '') {
                continue;
            }
            if ($description === '' || strlen($description) > 255) {
                throw new \RuntimeException('Each line needs a description.');
            }
            $quantity = $this->quantity($quantityRaw);
            $price = $this->money($priceRaw);
            $lineCents = (int) round(($this->cents($quantity) / 100) * $this->cents($price));
            $lines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_total' => number_format($lineCents / 100, 2, '.', ''),
            ];
        }
        if ($lines === []) {
            throw new \RuntimeException('Add at least one invoice line.');
        }

        return $lines;
    }

    private function money(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0.00';
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \RuntimeException('Enter amounts with up to two decimal places.');
        }
        $cents = $this->cents($value);
        if ($cents > 999999999999) {
            throw new \RuntimeException('That amount is too large.');
        }

        return number_format($cents / 100, 2, '.', '');
    }

    private function quantity(string $value): string
    {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \RuntimeException('Enter a quantity greater than zero.');
        }
        $cents = $this->cents($value);
        if ($cents <= 0 || $cents > 100000000) {
            throw new \RuntimeException('Enter a quantity greater than zero.');
        }

        return number_format($cents / 100, 2, '.', '');
    }

    public static function ensureDefaultTaxColumn(): void
    {
        Database::query('ALTER TABLE organizations ADD COLUMN IF NOT EXISTS default_tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00');
    }

    public static function defaultTaxRate(int $organizationId): string
    {
        self::ensureDefaultTaxColumn();
        $rate = Database::query(
            'SELECT default_tax_rate FROM organizations WHERE id = ? LIMIT 1',
            [$organizationId]
        )->fetchColumn();

        return number_format((float) $rate, 2, '.', '');
    }

    private function rememberTax(int $organizationId, string $taxRate): void
    {
        self::ensureDefaultTaxColumn();
        Database::query(
            'UPDATE organizations SET default_tax_rate = ? WHERE id = ?',
            [$taxRate, $organizationId]
        );
    }

    private function rate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '0.00';
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \RuntimeException('Enter a tax rate from 0 to 100.');
        }
        $cents = $this->cents($value);
        if ($cents > 10000) {
            throw new \RuntimeException('Enter a tax rate from 0 to 100.');
        }

        return number_format($cents / 100, 2, '.', '');
    }

    private function cents(string $value): int
    {
        $parts = explode('.', number_format((float) $value, 2, '.', ''));

        return ((int) $parts[0] * 100) + (int) $parts[1];
    }

    private function date(string $value, string $message): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || strtotime($value) === false) {
            throw new \RuntimeException($message);
        }

        return $value;
    }
}
