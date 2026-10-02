<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use App\Services\Invoicing\DocumentService;
use App\Services\Reporting\FinancialReports;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $company = app(CreateCompany::class)->execute($this->user, ['name' => 'Adjustments', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($company);
    $customer = Customer::create(['name' => 'Contact']);
    $this->data = ['customer_id' => $customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100', 'account_id' => Account::where('system_key', 'sales')->firstOrFail()->id]]];
    $this->service = app(DocumentService::class);
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('recalculates drafts without renumbering or posting', function () {
    $invoice = $this->service->create('invoice', $this->data);
    $this->data['items'][0]['unit_price'] = '200';
    $invoice = $this->service->updateDraft($invoice, $this->data);
    expect($invoice->total)->toBe('200.00')->and($invoice->invoice_number)->toBe('INV-000001');
    $this->assertDatabaseCount('journal_entries', 0);
});
it('voids unpaid posted invoices with a controlled reversal', function () {
    $invoice = $this->service->create('invoice', $this->data);
    $this->service->approve($invoice, $this->user->id);
    $this->service->void($invoice, '2026-10-02', $this->user->id);
    expect($invoice->fresh()->status)->toBe('void')->and(app(FinancialReports::class)->profitAndLoss('2026-01-01', '2026-12-31')['net_profit'])->toBe('0.00');
    $this->assertDatabaseCount('journal_entries',2);
});
