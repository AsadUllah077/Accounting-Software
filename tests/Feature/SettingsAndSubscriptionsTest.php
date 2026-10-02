<?php

use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use App\Services\Tenancy\MembershipService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->owner, ['name' => 'Workspace', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($this->company);
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('accepts invitations only for the addressed user and only once', function () {
    $member = User::factory()->create();
    $service = app(MembershipService::class);
    $token = $service->invite(['email' => $member->email, 'role_id' => Role::where('name', 'Accountant')->firstOrFail()->id]);
    expect($service->accept($member, $token))->toBe($this->company->id);
    expect(fn () => $service->accept($member, $token))->toThrow(ValidationException::class);
});
it('protects the last active owner', function () {
    app(MembershipService::class)->assign($this->owner->id, Role::where('name', 'Employee')->firstOrFail()->id, true);
})->throws(ValidationException::class);
it('creates trial subscriptions and idempotent renewal invoices outside tenant journals', function () {
    $service = app(SubscriptionService::class);
    $subscription = $service->choose(SubscriptionPlan::firstOrFail()->id);
    expect($subscription->status)->toBe('trialing');
    $this->travel(15)->days();
    expect($service->renewDue())->toBe(1)->and($service->renewDue())->toBe(0)->and(SubscriptionInvoice::count())->toBe(1);
    $invoice = SubscriptionInvoice::firstOrFail();
    $first = $service->recordPayment($invoice, 'verified-1');
    expect($service->recordPayment($invoice, 'verified-1')->id)->toBe($first->id)->and(SubscriptionPayment::count())->toBe(1);
    $this->assertDatabaseCount('journal_entries', 0);
    $this->travelBack();
});
it('enforces subscription plan document limits', function () {
    $service = app(SubscriptionService::class);
    $service->choose(SubscriptionPlan::firstOrFail()->id);
    $service->assertLimit('invoices_per_month', 100);
})->throws(ValidationException::class);
it('does not allow tenant owners to claim subscription payments', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->post('/subscription/invoices/1/payments', ['reference' => 'fake'])->assertForbidden();
});
it('marks unpaid billing past due before the next renewal and restores settled access', function () {
    $service = app(SubscriptionService::class);
    $this->travel(15)->days();
    $service->renewDue();
    $this->travel(1)->days();
    expect(fn () => $service->assertLimit('invoices_per_month', 0))->toThrow(ValidationException::class);
    $service->renewDue();
    expect(Subscription::firstOrFail()->status)->toBe('past_due');
    $service->recordPayment(SubscriptionInvoice::firstOrFail(), 'overdue-settlement');
    $service->assertLimit('invoices_per_month', 0);
    expect(Subscription::firstOrFail()->status)->toBe('active');
    $this->travelBack();
});
it('settles a cancelled subscription invoice without reactivating its plan', function () {
    $service = app(SubscriptionService::class);
    $this->travel(15)->days();
    $service->renewDue();
    $invoice = SubscriptionInvoice::firstOrFail();
    $service->cancel();
    $service->recordPayment($invoice, 'cancelled-settlement');
    expect($invoice->fresh()->status)->toBe('paid')->and($invoice->subscription->fresh()->status)->toBe('cancelled');
    $this->travelBack();
});
