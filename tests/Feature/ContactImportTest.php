<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->owner, ['name' => 'Import company', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id]);
});

it('imports validated contacts and safely skips duplicate name email pairs', function () {
    $csv = "name,email,phone,address,tax_number\nCustomer One,one@example.com,,,\nCustomer One,one@example.com,,,\nCustomer Two,two@example.com,,,\n";
    $this->post('/contacts/customers/import', ['file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])->assertSessionHasNoErrors()->assertRedirect();
    app(CompanyContext::class)->run($this->company, fn () => expect(Customer::count())->toBe(2));
    $this->assertDatabaseHas('customers', ['company_id' => $this->company->id, 'name' => 'Customer Two']);
});

it('validates the entire import before creating any contacts', function () {
    $csv = "name,email,phone,address,tax_number\nValid,valid@example.com,,,\nBroken,not-an-email,,,\n";
    $this->post('/contacts/customers/import', ['file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv)])->assertSessionHasErrors('email');
    $this->assertDatabaseCount('customers', 0);
});

it('rejects contact imports for read only company members', function () {
    $reader = User::factory()->create();
    $this->company->users()->attach($reader->id, ['role_id' => Role::where('name', 'Read-only User')->firstOrFail()->id, 'is_active' => true]);
    $this->actingAs($reader)->post('/contacts/customers/import', ['file' => UploadedFile::fake()->createWithContent('contacts.csv', "name,email,phone,address,tax_number\n")])->assertForbidden();
});
