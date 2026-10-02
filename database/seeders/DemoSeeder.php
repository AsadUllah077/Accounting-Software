<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Invoicing\DocumentService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo data is restricted to local/test environments.');
        }
        $this->call(FoundationSeeder::class);
        $password = Str::password(20);
        $user = User::firstOrCreate(['email' => 'demo@ledger.test'], ['name' => 'Alex Morgan', 'password' => Hash::make($password), 'email_verified_at' => now()]);
        if ($user->companies()->exists()) {
            $this->command?->info('Demo company already exists.');

            return;
        }
        $company = app(CreateCompany::class)->execute($user, ['name' => 'Northstar Studio', 'currency' => 'USD', 'timezone' => 'Asia/Karachi', 'financial_year_start_date' => now()->startOfYear()->toDateString()]);
        app(CompanyContext::class)->run($company, function () use ($user) {
            $customer = Customer::create(['name' => 'Acme Design Co.', 'email' => 'billing@example.test', 'address' => '42 Market Street']);
            $supplier = Supplier::create(['name' => 'Studio Supply', 'email' => 'accounts@example.test']);
            $service = app(DocumentService::class);
            $invoice = $service->create('invoice', ['customer_id' => $customer->id, 'date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'items' => [['description' => 'Brand strategy consulting', 'quantity' => '10', 'unit_price' => '150.00', 'account_id' => Account::where('system_key', 'sales')->firstOrFail()->id]]]);
            $service->approve($invoice, $user->id);
            $service->pay($invoice, ['amount' => '500.00', 'bank_account_id' => BankAccount::firstOrFail()->id, 'paid_at' => now()->toDateString(), 'idempotency_key' => 'demo-receipt'], $user->id);
            $bill = $service->create('bill', ['supplier_id' => $supplier->id, 'date' => now()->toDateString(), 'due_date' => now()->addDays(7)->toDateString(), 'items' => [['description' => 'Studio supplies', 'quantity' => '1', 'unit_price' => '250.00', 'account_id' => Account::where('system_key', 'expenses')->firstOrFail()->id]]]);
            $service->approve($bill, $user->id);
        });
        $this->command?->info('Demo login: demo@ledger.test / '.$password);
    }
}
