<?php

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\Accounting\JournalService;
use App\Services\Banking\ReconciliationService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->user, ['name' => 'Banking', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($this->company);
    $this->bank = BankAccount::firstOrFail();
    $this->service = app(ReconciliationService::class);
    $this->rows = [['date' => '2026-10-01', 'description' => 'Receipt', 'amount' => '100.00', 'reference' => 'statement-1']];
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('skips duplicate bank imports', function () {
    expect($this->service->import($this->bank, $this->rows))->toBe(1)->and($this->service->import($this->bank, $this->rows))->toBe(0)->and(BankTransaction::count())->toBe(1);
});
it('matches only equivalent posted bank lines once', function () {
    $this->service->import($this->bank, $this->rows);
    $entry = app(JournalService::class)->post('2026-10-01', 'receipt', [['account_id' => $this->bank->account_id, 'debit' => '100'], ['account_id' => Account::where('system_key', 'equity')->firstOrFail()->id, 'credit' => '100']], $this->user->id);
    $transaction = BankTransaction::firstOrFail();
    $line = $entry->lines->first();
    $match = $this->service->match($transaction, $line->id);
    expect((string) $match->matched_amount)->toBe('100.00');
    expect(fn () => $this->service->match($transaction, $line->id))->toThrow(ValidationException::class);
});
it('rejects statement and ledger amount mismatches', function () {
    $this->service->import($this->bank, $this->rows);
    $entry = app(JournalService::class)->post('2026-10-01', 'receipt', [['account_id' => $this->bank->account_id, 'debit' => '90'], ['account_id' => Account::where('system_key', 'equity')->firstOrFail()->id, 'credit' => '90']], $this->user->id);
    $this->service->match(BankTransaction::firstOrFail(), $entry->lines->first()->id);
})->throws(ValidationException::class);
