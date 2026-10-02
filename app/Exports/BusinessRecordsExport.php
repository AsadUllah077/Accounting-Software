<?php

namespace App\Exports;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class BusinessRecordsExport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private string $kind) {}

    public function query()
    {
        return match ($this->kind) {
            'invoices' => Invoice::with('customer')->orderBy('id'),'bills' => Bill::with('supplier')->orderBy('id'),'customers' => Customer::orderBy('id'),'suppliers' => Supplier::orderBy('id')
        };
    }

    public function headings(): array
    {
        return in_array($this->kind, ['invoices', 'bills']) ? ['Number', 'Contact', 'Date', 'Due', 'Status', 'Subtotal', 'Tax', 'Total', 'Outstanding', 'Currency'] : ['Name', 'Email', 'Phone', 'Address', 'Tax number', 'Status'];
    }

    public function map($record): array
    {
        if (in_array($this->kind, ['invoices', 'bills'])) {
            return [$record->invoice_number ?? $record->bill_number, $record->customer?->name ?? $record->supplier?->name, $record->issue_date ?? $record->bill_date, $record->due_date, $record->status, $record->subtotal, $record->tax_total, $record->total, $record->outstanding, $record->currency];
        }

        return [$record->name, $record->email, $record->phone, $record->address, $record->tax_number, $record->status];
    }
}
