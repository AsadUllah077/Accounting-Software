<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\Invoicing\DocumentService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = User::factory()->create();
    $this->outsider = User::factory()->create();
    $action = app(CreateCompany::class);
    $data = ['currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01'];
    $this->company = $action->execute($this->owner, ['name' => 'Company A'] + $data);
    $this->other = $action->execute($this->outsider, ['name' => 'Company B'] + $data);
    app(CompanyContext::class)->run($this->other, function () {
        $this->foreignContact = Customer::create(['name' => 'Foreign contact']);
        $this->foreignInvoice = app(DocumentService::class)->create('invoice', ['customer_id' => $this->foreignContact->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Private', 'quantity' => '1', 'unit_price' => '100', 'account_id' => Account::where('system_key', 'sales')->firstOrFail()->id]]]);
    });
});

it('rejects switching into a company without membership', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->post('/companies/'.$this->other->id.'/switch')->assertNotFound();
});
it('cannot read a foreign invoice by changing the URL', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->get('/documents/invoices/'.$this->foreignInvoice->id)->assertNotFound();
});
it('cannot modify foreign customer records', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->put('/contacts/customers/'.$this->foreignContact->id, ['name' => 'Intrusion', 'status' => 'active'])->assertNotFound();
    $this->assertDatabaseHas('customers', ['id' => $this->foreignContact->id, 'name' => 'Foreign contact']);
});
it('redirects an invalid active company to company selection', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->other->id])->get('/dashboard')->assertRedirect('/companies');
});
it('renders an empty company dashboard using actual data', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('summary.revenue', '0.00')->has('recentInvoices', 0));
});
it('blocks financial mutations for read-only members', function () {
    $this->company->users()->attach($this->outsider->id, ['role_id' => Role::where('name', 'Read-only User')->firstOrFail()->id, 'is_active' => true]);
    $this->actingAs($this->outsider)->withSession(['company_id' => $this->company->id])->post('/contacts/customers', ['name' => 'Forbidden', 'status' => 'active'])->assertForbidden();
});
it('fails closed when tenant context is absent', function () {
    app(CompanyContext::class)->clear();
    expect(fn () => Customer::count())->toThrow(LogicException::class);
});
