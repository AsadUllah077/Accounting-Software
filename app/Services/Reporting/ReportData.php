<?php

namespace App\Services\Reporting;

use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

final class ReportData
{
    public function build(string $report, string $from, string $to, ?int $contactId = null, ?int $accountId = null, ?int $page = null): mixed
    {
        $financial = app(FinancialReports::class);
        $operations = app(OperationalReports::class);
        if ($report === 'customer-statement') {
            Customer::findOrFail($contactId);
        }
        if ($report === 'supplier-statement') {
            Supplier::findOrFail($contactId);
        }

        return match ($report) {
            'trial-balance' => $financial->trialBalance($from, $to),'profit-and-loss' => $financial->profitAndLoss($from, $to),'balance-sheet' => $financial->balanceSheet($to),
            'accounts-receivable' => $operations->aging('receivables', $to),'accounts-payable' => $operations->aging('payables', $to),
            'expense-summary' => $operations->expenseSummary($from, $to),'tax-summary' => $operations->taxSummary($from, $to),'cash-flow' => $operations->cashFlow($from, $to),
            'customer-statement' => $operations->statement('customer', (int) $contactId, $from, $to),'supplier-statement' => $operations->statement('supplier', (int) $contactId, $from, $to),
            'general-ledger','account-transactions' => JournalLine::with(['account', 'entry'])->when($accountId, fn ($q) => $q->where('account_id', $accountId))->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$from, $to]))->orderBy('id')->paginate(50, ['*'], 'page', $page)->withQueryString(),
            default => abort(404),
        };
    }

    public function flatten(mixed $data): array
    {
        if ($data instanceof LengthAwarePaginator) {
            return $data->getCollection()->map(fn ($line) => ['Date' => $line->entry->date->toDateString(), 'Account' => $line->account->code.' '.$line->account->name, 'Reference' => $line->entry->reference, 'Debit' => $line->debit, 'Credit' => $line->credit])->all();
        }
        if (isset($data['rows'])) {
            return $data['rows'];
        }
        if (isset($data['revenue'])) {
            $rows = array_map(fn ($r) => ['Code' => $r['code'], 'Account' => $r['name'], 'Category' => $r['type'], 'Amount' => $r['type'] === 'revenue' ? bcsub('0', $r['balance'], 2) : $r['balance']], array_merge($data['revenue'], $data['expenses']));
            $rows[] = ['Code' => '', 'Account' => 'Total revenue', 'Category' => 'total', 'Amount' => $data['total_revenue']];
            $rows[] = ['Code' => '', 'Account' => 'Total expenses', 'Category' => 'total', 'Amount' => $data['total_expenses']];
            $rows[] = ['Code' => '', 'Account' => 'Net profit', 'Category' => 'total', 'Amount' => $data['net_profit']];

            return $rows;
        }
        if (isset($data['assets'])) {
            $rows = array_map(fn ($r) => ['Code' => $r['code'], 'Account' => $r['name'], 'Category' => $r['type'], 'Amount' => $r['type'] === 'asset' ? $r['balance'] : bcsub('0', $r['balance'], 2)], array_merge($data['assets'], $data['liabilities'], $data['equity']));
            $rows[] = ['Code' => '', 'Account' => 'Retained earnings', 'Category' => 'equity', 'Amount' => $data['retained_earnings']];
            foreach (['assets', 'liabilities', 'equity'] as $section) {
                $rows[] = ['Code' => '', 'Account' => 'Total '.$section, 'Category' => 'total', 'Amount' => $data['total_'.$section]];
            }

return $rows;
        }

        return $data;
    }
}
