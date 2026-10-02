<?php

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\User;
use App\Services\Invoicing\CreditService;
use App\Services\Invoicing\DocumentService;
use App\Services\Reporting\FinancialReports;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->user, ['name' => 'Credits', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($this->company);
    $customer = Customer::create(['name' => 'Customer']);
    $this->bank = BankAccount::firstOrFail();
    $this->documents = app(DocumentService::class);
    $this->credits = app(CreditService::class);
    $this->invoice = $this->documents->create('invoice', ['customer_id' => $customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100', 'account_id' => Account::where('system_key', 'sales')->firstOrFail()->id]]]);
    $this->documents->approve($this->invoice, $this->user->id);
    $this->creditData = ['date' => '2026-10-02', 'reason' => 'Adjustment', 'items' => [['invoice_item_id' => $this->invoice->items->first()->id, 'amount' => '40.00']]];
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('reduces outstanding balances and reverses the credited revenue', function () {
    $this->credits->issue($this->invoice, $this->creditData, $this->user->id);
    expect($this->invoice->fresh()->outstanding)->toBe('60.00')->and(app(FinancialReports::class)->profitAndLoss('2026-01-01', '2026-12-31')['total_revenue'])->toBe('60.00');
});
it('rejects over-crediting invoice lines', function () {
    $this->credits->issue($this->invoice, $this->creditData, $this->user->id);
    $this->creditData['items'][0]['amount'] = '61.00';
    $this->credits->issue($this->invoice, $this->creditData, $this->user->id);
})->throws(ValidationException::class);
it('refunds paid customer credits once', function () {
    $this->documents->pay($this->invoice, ['amount' => '100.00', 'paid_at' => '2026-10-01', 'bank_account_id' => $this->bank->id, 'idempotency_key' => 'pay'], $this->user->id);
    $note = $this->credits->issue($this->invoice, $this->creditData, $this->user->id);
    expect($this->invoice->fresh()->outstanding)->toBe('-40.00');
    $data = ['amount' => '40.00', 'date' => '2026-10-02', 'bank_account_id' => $this->bank->id, 'idempotency_key' => 'refund'];
    $refund = $this->credits->refund($note, $data, $this->user->id);
    expect($this->credits->refund($note, $data, $this->user->id)->id)->toBe($refund->id)->and($this->invoice->fresh()->outstanding)->toBe('0.00');
});
