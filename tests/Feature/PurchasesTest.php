<?php

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\ExpenseService;
use App\Services\Invoicing\DocumentService;
use App\Services\Reporting\FinancialReports;
use App\Services\Reporting\OperationalReports;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $company = app(CreateCompany::class)->execute($this->user, ['name' => 'Purchases', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($company);
    $this->supplier = Supplier::create(['name' => 'Supplier']);
    $this->bank = BankAccount::firstOrFail();
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('returns the same posted expense on retry and rejects a changed payload', function () {
    $data = ['category_id' => ExpenseCategory::firstOrFail()->id, 'bank_account_id' => $this->bank->id, 'date' => '2026-10-01', 'description' => 'Idempotent expense', 'amount' => '20.00', 'idempotency_key' => 'expense-retry'];
    $service = app(ExpenseService::class);
    $first = $service->create($data, $this->user->id);
    expect($service->create($data, $this->user->id)->id)->toBe($first->id);
    $data['amount'] = '25.00';
    expect(fn () => $service->create($data, $this->user->id))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseCount('journal_entries', 1);
});
it('shows the posted expense with private receipt access only in its own company', function () {
    $company = app(CompanyContext::class)->company();
    $expense = app(ExpenseService::class)->create(['category_id' => ExpenseCategory::firstOrFail()->id, 'bank_account_id' => $this->bank->id, 'date' => '2026-10-01', 'description' => 'Receipt expense', 'amount' => '15.00'], $this->user->id);
    $this->actingAs($this->user)->withSession(['company_id' => $company->id])->get('/expenses/'.$expense->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Purchases/ExpenseDetail')->where('expense.description', 'Receipt expense')->has('expense.attachments', 0));
    $other = app(CreateCompany::class)->execute($this->user, ['name' => 'Other company', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $this->withSession(['company_id' => $other->id])->get('/expenses/'.$expense->id)->assertNotFound();
});
it('posts bills and supplier payments without double-counting expenses', function () {
    $service = app(DocumentService::class);
    $bill = $service->create('bill', ['supplier_id' => $this->supplier->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Supplies', 'quantity' => '1', 'unit_price' => '100', 'account_id' => Account::where('system_key', 'expenses')->firstOrFail()->id]]]);
    $service->approve($bill, $this->user->id);
    $service->pay($bill, ['amount' => '40', 'paid_at' => '2026-10-02', 'bank_account_id' => $this->bank->id, 'idempotency_key' => 'supplier-payment'], $this->user->id);
    expect($bill->fresh()->outstanding)->toBe('60.00')->and(app(FinancialReports::class)->profitAndLoss('2026-01-01', '2026-12-31')['total_expenses'])->toBe('100.00');
    expect(app(OperationalReports::class)->cashFlow('2026-01-01', '2026-12-31')['closing_balance'])->toBe('-40.00');
});
it('posts paid expenses and recoverable tax exactly once', function () {
    $tax = TaxRate::create(['name' => 'Input VAT', 'rate' => '20', 'type' => 'purchases']);
    $expense = app(ExpenseService::class)->create(['category_id' => ExpenseCategory::firstOrFail()->id, 'bank_account_id' => $this->bank->id, 'tax_rate_id' => $tax->id, 'date' => '2026-10-01', 'description' => 'Office supplies', 'amount' => '100.00'], $this->user->id);
    expect((string) $expense->tax_amount)->toBe('20.00')->and(app(FinancialReports::class)->profitAndLoss('2026-01-01', '2026-12-31')['total_expenses'])->toBe('100.00');
    expect(fn () => $expense->update(['amount' => '1']))->toThrow(LogicException::class);
});
it('classifies partial capital purchase payments from their underlying bill lines', function () {
    $asset = Account::create(['code' => '1500', 'name' => 'Equipment', 'type' => 'asset', 'cash_flow_class' => 'investing']);
    $tax = TaxRate::create(['name' => 'Purchase VAT', 'rate' => '20', 'type' => 'purchases']);
    $service = app(DocumentService::class);
    $bill = $service->create('bill', ['supplier_id' => $this->supplier->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Equipment', 'quantity' => '1', 'unit_price' => '100', 'tax_rate_id' => $tax->id, 'account_id' => $asset->id]]]);
    $service->approve($bill, $this->user->id);
    $service->pay($bill, ['amount' => '60', 'paid_at' => '2026-10-02', 'bank_account_id' => $this->bank->id, 'idempotency_key' => 'capital-payment'], $this->user->id);
    $flow = app(OperationalReports::class)->cashFlow('2026-01-01', '2026-12-31');
    expect($flow['closing_balance'])->toBe('-60.00')->and(collect($flow['rows'])->firstWhere('classification', 'investing')['amount'])->toBe('-50.00')->and(collect($flow['rows'])->firstWhere('classification', 'operating')['amount'])->toBe('-10.00');
});
