<?php

namespace App\Services\Reporting;

use App\Models\Account;
use App\Models\JournalLine;
use App\Support\Decimal;

final class FinancialReports
{
    public function trialBalance(string $from, string $to): array
    {
        $totals = JournalLine::query()->whereHas('entry', fn ($q) => $q->where('status', 'posted')->whereBetween('date', [$from, $to]))
            ->selectRaw('account_id, SUM(debit) AS debits, SUM(credit) AS credits')->groupBy('account_id')->get()->keyBy('account_id');

        return Account::orderBy('code')->get()->map(function ($account) use ($totals) {
            $debits = Decimal::round((string) ($totals[$account->id]->debits ?? '0'));
            $credits = Decimal::round((string) ($totals[$account->id]->credits ?? '0'));

            return ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type, 'debits' => $debits, 'credits' => $credits, 'balance' => bcsub($debits, $credits, 2)];
        })->all();
    }

    public function profitAndLoss(string $from, string $to): array
    {
        $rows = $this->trialBalance($from, $to);
        $revenue = array_values(array_filter($rows, fn ($r) => $r['type'] === 'revenue'));
        $expenses = array_values(array_filter($rows, fn ($r) => $r['type'] === 'expense'));
        $income = bcsub('0', Decimal::sum(array_column($revenue, 'balance')), 2);
        $cost = Decimal::sum(array_column($expenses, 'balance'));

        return ['revenue' => $revenue, 'expenses' => $expenses, 'total_revenue' => $income, 'total_expenses' => $cost, 'net_profit' => bcsub($income, $cost, 2)];
    }

    public function balanceSheet(string $to): array
    {
        $rows = $this->trialBalance('0001-01-01', $to);
        $assets = array_values(array_filter($rows, fn ($r) => $r['type'] === 'asset'));
        $liabilities = array_values(array_filter($rows, fn ($r) => $r['type'] === 'liability'));
        $equity = array_values(array_filter($rows, fn ($r) => $r['type'] === 'equity'));
        $earnings = $this->profitAndLoss('0001-01-01', $to)['net_profit'];

        return ['assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'retained_earnings' => $earnings, 'total_assets' => Decimal::sum(array_column($assets, 'balance')), 'total_liabilities' => bcsub('0', Decimal::sum(array_column($liabilities, 'balance')), 2), 'total_equity' => bcadd(bcsub('0', Decimal::sum(array_column($equity,'balance')), 2), $earnings, 2)];
    }
}
