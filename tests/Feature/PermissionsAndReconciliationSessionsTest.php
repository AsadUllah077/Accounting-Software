<?php

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\JournalService;
use App\Services\Banking\SessionService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->owner, ['name' => 'Permissions', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($this->company);
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('applies role overrides only to their company', function () {
    $member = User::factory()->create();
    $role = Role::where('name', 'Read-only User')->firstOrFail();
    $this->company->users()->attach($member->id, ['role_id' => $role->id, 'is_active' => true]);
    $permission = Permission::where('name', 'contact.create')->firstOrFail();
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->put('/settings/roles/'.$role->id.'/permissions', ['permissions' => [$permission->id]])->assertRedirect();
    app(CompanyContext::class)->set($this->company);
    expect($member->hasCompanyPermission('contact.create'))->toBeTrue()->and($member->hasCompanyPermission('invoice.view'))->toBeFalse();
    $other = app(CreateCompany::class)->execute($this->owner, ['name' => 'Other', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $other->users()->attach($member->id, ['role_id' => $role->id, 'is_active' => true]);
    app(CompanyContext::class)->run($other, function () use ($member) {
        expect($member->hasCompanyPermission('contact.create'))->toBeFalse()->and($member->hasCompanyPermission('invoice.view'))->toBeTrue();
    });
});
it('protects owner permissions from removal', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->put('/settings/roles/'.Role::where('name', 'Owner')->firstOrFail()->id.'/permissions', ['permissions' => []])->assertSessionHasErrors('permissions');
});
it('closes sessions only when the statement and ledger balances agree', function () {
    $bank = BankAccount::firstOrFail();
    app(JournalService::class)->post('2026-10-01', 'opening', [['account_id' => $bank->account_id, 'debit' => '100'], ['account_id' => Account::where('system_key', 'equity')->firstOrFail()->id, 'credit' => '100']], $this->owner->id);
    $service = app(SessionService::class);
    $session = $service->open(['bank_account_id' => $bank->id, 'statement_date' => '2026-10-01', 'closing_balance' => '100.00']);
    $service->close($session);
    expect($session->fresh()->status)->toBe('closed');
    expect(fn () => $service->close($session))->toThrow(ValidationException::class);
    $bad = $service->open(['bank_account_id' => $bank->id, 'statement_date' => '2026-10-01', 'closing_balance' => '99.00']);
    expect(fn () => $service->close($bad))->toThrow(ValidationException::class);
});
