<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\FinancialYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Reporting\FinancialReports;
use App\Services\Tenancy\CompanyContext;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinancialReports $reports)
    {
        abort_unless($request->user()->hasCompanyPermission('report.view'), 403);
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $profit = $reports->profitAndLoss($from, $to);
        $balances = collect($reports->trialBalance('0001-01-01', $to))->keyBy('id');
        $banks = BankAccount::get()->map(fn ($bank) => ['id' => $bank->id, 'name' => $bank->name, 'balance' => $balances[$bank->account_id]['balance'] ?? '0.00']);
        $outstanding = fn ($class) => Decimal::round((string) $class::whereNotIn('status', ['draft', 'void'])->selectRaw('COALESCE(SUM(total-paid_total-credited_total),0) AS outstanding')->toBase()->value('outstanding'));
        $monthly = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to);
        // Bound chart work for wide report ranges; totals still honor the complete range.
        while ($cursor <= $end && count($monthly) < 24) {
            $period = $reports->profitAndLoss(max($from, $cursor->toDateString()), min($to, $cursor->copy()->endOfMonth()->toDateString()));
            $monthly[] = ['month' => $cursor->format('M Y'), 'revenue' => $period['total_revenue'], 'expenses' => $period['total_expenses']];
            $cursor->addMonth();
        }

        return Inertia::render('Dashboard', [
            'financialYears' => FinancialYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date']),
            'summary' => ['revenue' => $profit['total_revenue'], 'expenses' => $profit['total_expenses'], 'profit' => $profit['net_profit'], 'receivables' => $outstanding(Invoice::class), 'payables' => $outstanding(Bill::class), 'cash' => Decimal::sum($banks->pluck('balance'))],
            'banks' => $banks, 'monthly' => $monthly, 'recentInvoices' => Invoice::with('customer')->latest('id')->limit(5)->get(),
            'recentTransactions' => JournalEntry::where('status', 'posted')->latest('id')->limit(5)->get(),
            'overdueInvoices' => Invoice::with('customer')->whereNotIn('status', ['draft', 'void', 'paid'])->where('due_date', '<', now()->toDateString())->orderBy('due_date')->limit(5)->get(),
            'upcomingBills' => Bill::with('supplier')->whereNotIn('status', ['draft', 'void', 'paid'])->where('due_date', '>=', now()->toDateString())->orderBy('due_date')->limit(5)->get(),
            'filters' => compact('from', 'to'), 'companyName' => app(CompanyContext::class)->company()->name,
        ]);
    }
}
