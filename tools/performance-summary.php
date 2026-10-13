<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = App\Models\Company::where('name', 'Ledger Performance Test')->firstOrFail();
app(App\Services\Tenancy\CompanyContext::class)->set($company);

$pl = app(App\Services\Reporting\FinancialReports::class)->profitAndLoss('2026-01-01', '2026-12-31');
$bs = app(App\Services\Reporting\FinancialReports::class)->balanceSheet('2026-12-31');

echo 'Company: '.$company->name.PHP_EOL;
echo 'Customers: '.App\Models\Customer::count().PHP_EOL;
echo 'Vendors: '.App\Models\Supplier::count().PHP_EOL;
echo 'Invoices: '.App\Models\Invoice::count().PHP_EOL;
echo 'Bills: '.App\Models\Bill::count().PHP_EOL;
echo 'Expenses: '.App\Models\Expense::count().PHP_EOL;
echo 'Journal entries: '.App\Models\JournalEntry::count().PHP_EOL;
echo 'Revenue: '.$pl['total_revenue'].PHP_EOL;
echo 'Expenses total: '.$pl['total_expenses'].PHP_EOL;
echo 'Net profit: '.$pl['net_profit'].PHP_EOL;
echo 'Assets: '.$bs['total_assets'].PHP_EOL;
echo 'Liabilities: '.$bs['total_liabilities'].PHP_EOL;
echo 'Equity including earnings: '.$bs['total_equity'].PHP_EOL;
