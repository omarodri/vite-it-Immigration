/**
 * Invoice Types
 * Interfaces for case invoice / account statement data structures
 */

export interface CaseInvoice {
    id?: number;
    invoice_number: string;
    invoice_date: string;   // formato YYYY-MM-DD
    amount: number;
    is_collected: boolean;
    sort_order?: number;
}

export interface FinancialSummary {
    fees: number | null;
    total_invoiced: number;
    total_collected: number;
    balance: number | null;
}

export interface BulkUpdateInvoicesData {
    invoices: Omit<CaseInvoice, 'id' | 'sort_order'>[];
}
