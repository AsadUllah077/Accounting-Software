<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Invoicing\DocumentService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->user = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->user, [
        'name' => 'Document Filters',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'financial_year_start_date' => '2026-01-01',
    ]);
    app(CompanyContext::class)->set($this->company);
    $this->service = app(DocumentService::class);
});

afterEach(fn () => app(CompanyContext::class)->clear());

it('filters sales invoices by exact customer even when names match', function () {
    $sales = Account::where('system_key', 'sales')->firstOrFail();
    $alpha = Customer::create(['name' => 'Alpha Retail']);
    $beta = Customer::create(['name' => 'Alpha Retail']);

    $this->service->create('invoice', ['customer_id' => $alpha->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100', 'account_id' => $sales->id]]]);
    $this->service->create('invoice', ['customer_id' => $beta->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100', 'account_id' => $sales->id]]]);

    $this->actingAs($this->user)
        ->withSession(['company_id' => $this->company->id])
        ->get('/documents/invoices?contact_id='.$alpha->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Documents/Index')
            ->where('selectedContact.id', $alpha->id)
            ->has('documents.data', 1)
            ->where('documents.data.0.customer.name', 'Alpha Retail'));
});

it('filters bills by exact vendor', function () {
    $expense = Account::where('system_key', 'expenses')->firstOrFail();
    $north = Supplier::create(['name' => 'North Vendor']);
    $south = Supplier::create(['name' => 'South Vendor']);

    $this->service->create('bill', ['supplier_id' => $north->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Supplies', 'quantity' => '1', 'unit_price' => '100', 'account_id' => $expense->id]]]);
    $this->service->create('bill', ['supplier_id' => $south->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Supplies', 'quantity' => '1', 'unit_price' => '100', 'account_id' => $expense->id]]]);

    $this->actingAs($this->user)
        ->withSession(['company_id' => $this->company->id])
        ->get('/documents/bills?contact_id='.$north->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Documents/Index')
            ->where('selectedContact.id', $north->id)
            ->has('documents.data', 1)
            ->where('documents.data.0.supplier.name', 'North Vendor'));
});

it('loads bounded contact batches with search and cursors', function (string $kind, string $model) {
    for ($i = 1; $i <= 30; $i++) {
        $model::create(['name' => sprintf('Batch %02d', $i)]);
    }
    $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    $first = $this->getJson('/documents/'.$kind.'/contact-options')
        ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('data.0.name', 'Batch 01');
    $this->getJson('/documents/'.$kind.'/contact-options?after='.$first->json('next'))
        ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.name', 'Batch 26')->assertJsonPath('next', null);
    $this->getJson('/documents/'.$kind.'/contact-options?search=Batch%2030')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Batch 30');

    $otherCompany = app(CreateCompany::class)->execute($this->user, [
        'name' => 'Other Company', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01',
    ]);
    app(CompanyContext::class)->set($otherCompany);
    $foreign = $model::create(['name' => 'Foreign Contact']);
    app(CompanyContext::class)->set($this->company);
    $this->getJson('/documents/'.$kind.'/contact-options?search=Foreign')->assertOk()->assertJsonCount(0, 'data');
    $this->get('/documents/'.$kind.'?contact_id='.$foreign->id)->assertNotFound();
})->with([['invoices', Customer::class], ['bills', Supplier::class]]);
