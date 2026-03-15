<?php

namespace App\Services\Case;

use App\Models\ImmigrationCase;

class CaseInvoiceService
{
    /**
     * Replace all invoices for a case (delete-and-insert).
     */
    public function syncInvoices(ImmigrationCase $case, array $invoices): void
    {
        $case->invoices()->delete();

        if (! empty($invoices)) {
            $normalized = array_values($invoices);
            foreach ($normalized as $idx => $invoice) {
                $case->invoices()->create([
                    'invoice_number' => $invoice['invoice_number'],
                    'invoice_date' => $invoice['invoice_date'],
                    'amount' => $invoice['amount'],
                    'is_collected' => array_key_exists('is_collected', $invoice) ? $invoice['is_collected'] : false,
                    'sort_order' => $idx,
                ]);
            }
        }
    }
}
