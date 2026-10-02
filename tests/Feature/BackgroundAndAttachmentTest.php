<?php

use App\Jobs\GenerateReportExport;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Invoicing\DocumentService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->owner = User::factory()->create();
    $this->company = app(CreateCompany::class)->execute($this->owner, ['name' => 'Artifacts', 'currency' => 'USD', 'timezone' => 'UTC', 'financial_year_start_date' => '2026-01-01']);
    app(CompanyContext::class)->set($this->company);
    $customer = Customer::create(['name' => 'Customer']);
    $this->invoice = app(DocumentService::class)->create('invoice', ['customer_id' => $customer->id, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100', 'account_id' => Account::where('system_key', 'sales')->firstOrFail()->id]]]);
    app(DocumentService::class)->approve($this->invoice, $this->owner->id);
});
afterEach(fn () => app(CompanyContext::class)->clear());
it('generates a complete private ledger export with explicit company context', function () {
    Storage::fake('local');
    $export = ReportExport::create(['user_id' => $this->owner->id, 'report' => 'general-ledger', 'format' => 'xlsx', 'filters' => ['from' => '2026-01-01', 'to' => '2026-12-31']]);
    app(CompanyContext::class)->clear();
    (new GenerateReportExport($this->company->id, $export->id))->handle();
    app(CompanyContext::class)->run($this->company, function () use ($export) {
        $export = $export->fresh();
        expect($export->status)->toBe('ready');
        Storage::disk('local')->assertExists($export->path);
    });
    expect(fn () => app(CompanyContext::class)->id())->toThrow(LogicException::class);
});
it('stores private attachments and authorizes their downloads', function () {
    Storage::fake('local');
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->post('/attachments/invoice/'.$this->invoice->id, ['file' => UploadedFile::fake()->image('receipt.png')])->assertRedirect();
    app(CompanyContext::class)->set($this->company);
    $file = Attachment::firstOrFail();
    $this->get('/attachments/'.$file->id)->assertOk();
    $other = User::factory()->create();
    $this->actingAs($other)->withSession(['company_id' => $this->company->id])->get('/attachments/'.$file->id)->assertRedirect('/companies');
});
it('refuses executable uploads', function () {
    $this->actingAs($this->owner)->withSession(['company_id' => $this->company->id])->post('/attachments/invoice/'.$this->invoice->id, ['file' => UploadedFile::fake()->create('script.php', 1, 'application/x-php')])->assertSessionHasErrors('file');
});
