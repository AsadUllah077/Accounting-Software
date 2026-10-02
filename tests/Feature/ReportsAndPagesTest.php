<?php

use App\Models\User;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->user, ['name' => 'Reports', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
});
it('renders implemented application pages', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/contacts/customers', '/contacts/suppliers', '/documents/invoices', '/documents/bills', '/documents/invoices/create', '/accounting/accounts', '/accounting/journals', '/banking', '/expenses', '/reports', '/settings', '/subscription', '/credit-notes']);
it('renders all financial and operational reports', function (string $report) {
    $this->get('/reports/'.$report)->assertOk();
})->with(['trial-balance', 'profit-and-loss', 'balance-sheet', 'general-ledger', 'account-transactions', 'accounts-receivable', 'accounts-payable', 'expense-summary', 'tax-summary', 'cash-flow']);
it('generates real PDF and spreadsheet report artifacts', function (string $format) {
    $response = $this->get('/reports/trial-balance?format='.$format);
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('trial-balance.'.$format);
})->with(['pdf', 'xlsx']);
