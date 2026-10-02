<?php

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\JournalService;
use App\Services\Invoicing\DocumentService;
use App\Services\Reporting\FinancialReports;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->user, ['name' => 'Company A', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($this->company);
    $this->customer = Customer::create(['name' => 'Customer A']);
    $this->sale = Account::where('system_key', 'sales')->firstOrFail();
    $this->bank = BankAccount::firstOrFail();
    $this->service = app(DocumentService::class);
    $this->draft = fn () => $this->service->create('invoice', ['customer_id' => $this->customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Consulting', 'quantity' => '2.5', 'unit_price' => '19.99', 'discount' => '1.00', 'account_id' => $this->sale->id]]]);
});

afterEach(fn () => app(CompanyContext::class)->clear());

it('calculates decimal totals and snapshots tax rates', function () {
    $tax = TaxRate::create(['name' => 'VAT', 'rate' => '20.0000']);
    $invoice = $this->service->create('invoice', ['customer_id' => $this->customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '3', 'unit_price' => '0.10', 'tax_rate_id' => $tax->id, 'account_id' => $this->sale->id]]]);
    expect($invoice->subtotal)->toBe('0.30')->and($invoice->tax_total)->toBe('0.06')->and($invoice->total)->toBe('0.36');
    expect(($this->draft)()->total)->toBe('48.98');
});

it('generates unique company invoice numbers', function () {
    expect(($this->draft)()->invoice_number)->toBe('INV-000001')->and(($this->draft)()->invoice_number)->toBe('INV-000002');
});

it('posts invoices and supports partial full and idempotent payments', function () {
    $invoice = ($this->draft)();
    $this->service->approve($invoice, $this->user->id);
    $data = ['amount' => '20.00', 'paid_at' => '2026-10-01', 'bank_account_id' => $this->bank->id, 'idempotency_key' => 'receipt-1'];
    $first = $this->service->pay($invoice, $data, $this->user->id);
    expect($this->service->pay($invoice, $data, $this->user->id)->id)->toBe($first->id);
    expect($invoice->fresh()->status)->toBe('partially_paid')->and($invoice->fresh()->outstanding)->toBe('28.98');
    $data['amount'] = '28.98';
    $data['idempotency_key'] = 'receipt-2';
    $this->service->pay($invoice, $data, $this->user->id);
    expect($invoice->fresh()->outstanding)->toBe('0.00')->and($invoice->fresh()->status)->toBe('paid');
});

it('rejects an idempotency key reused with another payload', function () {
    $invoice = ($this->draft)();
    $this->service->approve($invoice, $this->user->id);
    $data = ['amount' => '10.00', 'paid_at' => '2026-10-01', 'bank_account_id' => $this->bank->id, 'idempotency_key' => 'receipt'];
    $this->service->pay($invoice, $data, $this->user->id);
    $data['amount'] = '11.00';
    $this->service->pay($invoice, $data, $this->user->id);
})->throws(ValidationException::class);

it('rejects unbalanced journals atomically', function () {
    try {
        app(JournalService::class)->post('2026-10-01', 'bad', [['account_id' => $this->bank->account_id, 'debit' => '10'], ['account_id' => $this->sale->id, 'credit' => '9']], $this->user->id);
    } catch (ValidationException $e) {
        expect(JournalEntry::count())->toBe(0);

        return;
    }
    $this->fail('Unbalanced journal was accepted.');
});

it('rejects accounts from another company', function () {
    $other = app(CreateCompany::class)->execute($this->user, ['name' => 'Company B', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $foreignId = app(CompanyContext::class)->run($other, fn () => Account::firstOrFail()->id);
    app(JournalService::class)->post('2026-10-01', 'bad', [['account_id' => $foreignId, 'debit' => '10'], ['account_id' => $this->sale->id, 'credit' => '10']], $this->user->id);
})->throws(ModelNotFoundException::class);

it('protects posted entries and lines', function () {
    $invoice = ($this->draft)();
    $this->service->approve($invoice, $this->user->id);
    $entry = $invoice->fresh()->journal;
    expect(fn () => $entry->update(['description' => 'tamper']))->toThrow(LogicException::class);
    expect(fn () => $entry->lines->first()->update(['debit' => '1']))->toThrow(LogicException::class);
    expect(fn () => $invoice->fresh()->update(['total' => '1']))->toThrow(LogicException::class);
});

it('reverses manual journals once', function () {
    $service = app(JournalService::class);
    $entry = $service->post('2026-10-01', 'opening', [['account_id' => $this->bank->account_id, 'debit' => '100'], ['account_id' => Account::where('system_key', 'equity')->firstOrFail()->id, 'credit' => '100']], $this->user->id);
    $reversal = $service->reverse($entry, '2026-10-02', $this->user->id);
    expect($reversal->lines->first()->credit)->toBe('100.00');
    expect(fn () => $service->reverse($entry, '2026-10-03', $this->user->id))->toThrow(ValidationException::class);
});

it('reports only posted accounting transactions', function () {
    $invoice = ($this->draft)();
    $reports = app(FinancialReports::class);
    expect($reports->profitAndLoss('2026-01-01', '2026-12-31')['total_revenue'])->toBe('0.00');
    $this->service->approve($invoice, $this->user->id);
    expect($reports->profitAndLoss('2026-01-01', '2026-12-31')['total_revenue'])->toBe('48.98');
    $sheet = $reports->balanceSheet('2026-12-31');
    expect($sheet['total_assets'])->toBe(bcadd($sheet['total_liabilities'], $sheet['total_equity'], 2));
});

it('enforces accounting locks', function () {
    $this->company->forceFill(['locked_through' => '2026-10-01'])->save();
    $this->service->approve(($this->draft)(),$this->user->id);
})->throws(ValidationException::class);
