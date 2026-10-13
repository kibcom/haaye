<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class InvoiceItem extends Model
{
    public ?int $id = null;
    public int $organization_id = 0;
    public int $invoice_id = 0;
    public string $description = '';
    public string $quantity = '1.00';
    public string $unit_price = '0.00';
    public string $line_total = '0.00';
    public int $sort_order = 0;

    public static function table(): string
    {
        return 'invoice_items';
    }

    /** @return list<self> */
    public static function forInvoice(int $invoiceId, int $organizationId): array
    {
        $rows = Database::query(
            'SELECT * FROM invoice_items WHERE invoice_id = ? AND organization_id = ? ORDER BY sort_order ASC, id ASC',
            [$invoiceId, $organizationId]
        )->fetchAll();

        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function replaceForInvoice(int $organizationId, int $invoiceId, array $lines): void
    {
        Database::query('DELETE FROM invoice_items WHERE invoice_id = ? AND organization_id = ?', [$invoiceId, $organizationId]);
        $order = 0;
        foreach ($lines as $line) {
            Database::query(
                'INSERT INTO invoice_items (organization_id, invoice_id, description, quantity, unit_price, line_total, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $organizationId,
                    $invoiceId,
                    $line['description'],
                    $line['quantity'],
                    $line['unit_price'],
                    $line['line_total'],
                    $order,
                ]
            );
            $order++;
        }
    }
}
