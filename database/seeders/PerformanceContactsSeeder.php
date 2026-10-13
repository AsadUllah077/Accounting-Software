<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\CreateCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PerformanceContactsSeeder extends Seeder
{
    private int $companyId;
    private string $now;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Performance contacts are restricted to local/test environments.');
        }

        $this->call(FoundationSeeder::class);

        $records = max(1, (int) env('PERFORMANCE_CONTACTS_PER_TYPE', 10000));
        $this->now = now()->toDateTimeString();

        $user = User::firstOrCreate(
            ['email' => 'performance@ledger.test'],
            ['name' => 'Performance Tester', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );

        $company = Company::where('name', 'Ledger Performance Test')->first();
        if (! $company) {
            $company = app(CreateCompany::class)->execute($user, [
                'name' => 'Ledger Performance Test',
                'currency' => 'USD',
                'timezone' => 'Asia/Karachi',
                'financial_year_start_date' => '2026-01-01',
            ]);
        } elseif (! $company->users()->whereKey($user->id)->exists()) {
            $company->users()->attach($user->id, [
                'role_id' => Role::where('name', 'Owner')->firstOrFail()->id,
                'is_active' => true,
            ]);
        }

        $this->companyId = $company->id;

        app(CompanyContext::class)->run($company, function () use ($records): void {
            DB::disableQueryLog();

            $this->command?->info("Creating up to {$records} performance customers...");
            $customers = $this->seedContacts('customers', 'Performance Customer', 'customer', $records);

            $this->command?->info("Creating up to {$records} performance vendors...");
            $vendors = $this->seedContacts('suppliers', 'Performance Vendor', 'vendor', $records);

            $this->command?->info("Added {$customers} customers and {$vendors} vendors to Ledger Performance Test.");
        });
    }

    private function seedContacts(string $table, string $namePrefix, string $emailPrefix, int $records): int
    {
        $existing = DB::table($table)
            ->where('company_id', $this->companyId)
            ->where('name', 'like', $namePrefix.' %')
            ->pluck('name')
            ->flip();

        $rows = [];
        $inserted = 0;

        for ($i = 1; $i <= $records; $i++) {
            $suffix = str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $name = "{$namePrefix} {$suffix}";

            if (isset($existing[$name])) {
                continue;
            }

            $rows[] = [
                'company_id' => $this->companyId,
                'name' => $name,
                'email' => "{$emailPrefix}.{$suffix}@example.test",
                'phone' => '+1-555-'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'address' => "{$i} Performance Avenue",
                'tax_number' => strtoupper($emailPrefix).'-'.$suffix,
                'status' => 'active',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            if (count($rows) === 500) {
                DB::table($table)->insert($rows);
                $inserted += count($rows);
                $this->progress($inserted, $records, $table);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table($table)->insert($rows);
            $inserted += count($rows);
        }

        $this->progress($inserted, $records, $table);

        return $inserted;
    }

    private function progress(int $done, int $total, string $label): void
    {
        if ($done % 1000 === 0 || $done === $total || $done === 0) {
            $this->command?->info("  {$done}/{$total} {$label}");
        }
    }
}
