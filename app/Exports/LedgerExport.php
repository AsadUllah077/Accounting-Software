<?php

namespace App\Exports;

use App\Models\JournalLine;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class LedgerExport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private string $from, private string $to, private ?int $accountId = null) {}

    public function query()
    {
        return JournalLine::with(['entry', 'account'])->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$this->from, $this->to]))->orderBy('id');
    }

    public function headings(): array
    {
        return ['Date', 'Account', 'Reference', 'Debit', 'Credit'];
    }

    public function map($line): array
    {
        return [$line->entry->date->toDateString(), $line->account->code.' '.$line->account->name, $line->entry->reference, $line->debit, $line->credit];
    }
}
