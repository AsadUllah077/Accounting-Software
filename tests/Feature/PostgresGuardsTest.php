<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\User;
use App\Services\Invoicing\DocumentService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-specific database safeguards.');
    }
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $company = app(CreateCompany::class)->execute($this->user, ['name' => 'Guards', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($company);
    $customer = Customer::create(['name' => 'Customer']);
    $service = app(DocumentService::class);
    $this->invoice = $service->create('invoice', ['customer_id' => $customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100', 'account_id' => Account::where('system_key', 'sales')->firstOrFail()->id]]]);
    $service->approve($this->invoice, $this->user->id);
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('rejects direct SQL changes to posted journals', function () {
    expect(fn () => DB::transaction(fn () => DB::table('journal_entries')->where('id', $this->invoice->fresh()->journal_entry_id)->update(['reference' => 'tamper'])))->toThrow(QueryException::class);
});
it('rejects direct SQL changes to posted journal lines', function () {
    expect(fn () => DB::transaction(fn () => DB::table('journal_lines')->where('journal_entry_id', $this->invoice->fresh()->journal_entry_id)->update(['debit' => '1'])))->toThrow(QueryException::class);
});
it('rejects direct SQL changes to audit logs', function () {
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->where('company_id', app(CompanyContext::class)->id())->delete()))->toThrow(QueryException::class);
});
