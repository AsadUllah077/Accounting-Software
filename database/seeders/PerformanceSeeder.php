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

class PerformanceSeeder extends Seeder
{
    private int $companyId;
    private int $userId;
    private string $currency;
    private string $now;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Performance data is restricted to local/test environments.');
        }

        $this->call(FoundationSeeder::class);

        $records = max(1, (int) env('PERFORMANCE_RECORDS_PER_MODULE', 10000));
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
        $this->userId = $user->id;
        $this->currency = $company->currency;

        app(CompanyContext::class)->run($company, function () use ($records): void {
            DB::disableQueryLog();
            $ids = $this->referenceIds();

            $this->command?->info("Creating {$records} posted sales invoices...");
            $this->seedInvoices($records, $ids);

            $this->command?->info("Creating {$records} posted supplier bills...");
            $this->seedBills($records, $ids);

            $this->command?->info("Creating {$records} posted direct expenses...");
            $this->seedExpenses($records, $ids);
        });

        $this->command?->info('Performance login: performance@ledger.test / password');
        $this->command?->info("Company: Ledger Performance Test. Added {$records} invoices, {$records} bills, and {$records} expenses.");
    }

    private function referenceIds(): array
    {
        $customerId = $this->ensureContact('customers', 'Performance Customer');
        $supplierId = $this->ensureContact('suppliers', 'Performance Supplier');
        $taxSalesId = $this->ensureTaxRate('Performance Sales VAT 10%', '10.0000', 'sales');
        $taxPurchaseId = $this->ensureTaxRate('Performance Purchase VAT 10%', '10.0000', 'purchases');

        return [
            'customer' => $customerId,
            'supplier' => $supplierId,
            'sales_tax' => $taxSalesId,
            'purchase_tax' => $taxPurchaseId,
            'bank_account' => (int) DB::table('bank_accounts')->where('company_id', $this->companyId)->where('name', 'Operating bank')->value('id'),
            'category' => (int) DB::table('expense_categories')->where('company_id', $this->companyId)->where('name', 'General expenses')->value('id'),
            'bank' => $this->account('bank'),
            'receivables' => $this->account('receivables'),
            'input_tax' => $this->account('input_tax'),
            'payables' => $this->account('payables'),
            'output_tax' => $this->account('output_tax'),
            'sales' => $this->account('sales'),
            'expenses' => $this->account('expenses'),
        ];
    }

    private function seedInvoices(int $records, array $ids): void
    {
        $sequence = $this->nextSequence('invoice');

        for ($i = 0; $i < $records; $i++) {
            $number = 'INV-'.str_pad((string) ($sequence + $i), 6, '0', STR_PAD_LEFT);
            $date = $this->dateFor($i);
            $subtotal = $this->amountFor($i, 125);
            $taxed = $i % 4 === 0;
            $taxAmount = $taxed ? $this->percent($subtotal, 10) : '0.00';
            $total = $this->add($subtotal, $taxAmount);

            $invoiceId = DB::table('invoices')->insertGetId([
                'company_id' => $this->companyId,
                'customer_id' => $ids['customer'],
                'invoice_number' => $number,
                'issue_date' => $date,
                'due_date' => $this->dueDate($date, 14),
                'currency' => $this->currency,
                'status' => $i % 2 === 0 ? 'paid' : 'approved',
                'subtotal' => $subtotal,
                'tax_total' => $taxAmount,
                'total' => $total,
                'paid_total' => $i % 2 === 0 ? $total : '0.00',
                'credited_total' => '0.00',
                'notes' => 'Performance sales invoice',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('invoice_items')->insert([
                'company_id' => $this->companyId,
                'invoice_id' => $invoiceId,
                'account_id' => $ids['sales'],
                'tax_rate_id' => $taxed ? $ids['sales_tax'] : null,
                'description' => 'Performance consulting service',
                'quantity' => '1.0000',
                'unit_price' => $subtotal,
                'discount' => '0.00',
                'tax_rate' => $taxed ? '10.0000' : '0.0000',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $journalId = $this->journal($date, $number, 'invoice', $invoiceId);
            $lines = [
                $this->line($journalId, $ids['receivables'], $total, '0.00'),
                $this->line($journalId, $ids['sales'], '0.00', $subtotal),
            ];
            if ($taxed) {
                $lines[] = $this->line($journalId, $ids['output_tax'], '0.00', $taxAmount);
            }
            DB::table('journal_lines')->insert($lines);
            DB::table('invoices')->where('id', $invoiceId)->update(['journal_entry_id' => $journalId, 'updated_at' => $this->now]);

            if ($i % 2 === 0) {
                $paymentJournalId = $this->journal($date, 'PAY-'.$number, 'invoice_payment', null);
                DB::table('journal_lines')->insert([
                    $this->line($paymentJournalId, $ids['bank'], $total, '0.00'),
                    $this->line($paymentJournalId, $ids['receivables'], '0.00', $total),
                ]);
                DB::table('payments')->insert([
                    'company_id' => $this->companyId,
                    'invoice_id' => $invoiceId,
                    'bank_account_id' => $ids['bank_account'],
                    'journal_entry_id' => $paymentJournalId,
                    'payment_method' => 'bank',
                    'amount' => $total,
                    'paid_at' => $date,
                    'reference' => 'PERF-'.$number,
                    'idempotency_key' => 'perf-payment-'.$number,
                    'request_hash' => hash('sha256', $number.$total.$date),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            $this->progress($i + 1, $records, 'invoices');
        }

        $this->advanceSequence('invoice', $sequence + $records);
    }

    private function seedBills(int $records, array $ids): void
    {
        $sequence = $this->nextSequence('bill');

        for ($i = 0; $i < $records; $i++) {
            $number = 'BILL-'.str_pad((string) ($sequence + $i), 6, '0', STR_PAD_LEFT);
            $date = $this->dateFor($i);
            $subtotal = $this->amountFor($i, 75);
            $taxed = $i % 3 === 0;
            $taxAmount = $taxed ? $this->percent($subtotal, 10) : '0.00';
            $total = $this->add($subtotal, $taxAmount);

            $billId = DB::table('bills')->insertGetId([
                'company_id' => $this->companyId,
                'supplier_id' => $ids['supplier'],
                'bill_number' => $number,
                'bill_date' => $date,
                'due_date' => $this->dueDate($date, 21),
                'currency' => $this->currency,
                'status' => $i % 2 === 0 ? 'paid' : 'approved',
                'subtotal' => $subtotal,
                'tax_total' => $taxAmount,
                'total' => $total,
                'paid_total' => $i % 2 === 0 ? $total : '0.00',
                'credited_total' => '0.00',
                'notes' => 'Performance supplier bill',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('bill_items')->insert([
                'company_id' => $this->companyId,
                'bill_id' => $billId,
                'account_id' => $ids['expenses'],
                'tax_rate_id' => $taxed ? $ids['purchase_tax'] : null,
                'description' => 'Performance operating cost',
                'quantity' => '1.0000',
                'unit_price' => $subtotal,
                'discount' => '0.00',
                'tax_rate' => $taxed ? '10.0000' : '0.0000',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $journalId = $this->journal($date, $number, 'bill', $billId);
            $lines = [
                $this->line($journalId, $ids['expenses'], $subtotal, '0.00'),
                $this->line($journalId, $ids['payables'], '0.00', $total),
            ];
            if ($taxed) {
                $lines[] = $this->line($journalId, $ids['input_tax'], $taxAmount, '0.00');
            }
            DB::table('journal_lines')->insert($lines);
            DB::table('bills')->where('id', $billId)->update(['journal_entry_id' => $journalId, 'updated_at' => $this->now]);

            if ($i % 2 === 0) {
                $paymentJournalId = $this->journal($date, 'PAY-'.$number, 'bill_payment', null);
                DB::table('journal_lines')->insert([
                    $this->line($paymentJournalId, $ids['payables'], $total, '0.00'),
                    $this->line($paymentJournalId, $ids['bank'], '0.00', $total),
                ]);
                DB::table('bill_payments')->insert([
                    'company_id' => $this->companyId,
                    'bill_id' => $billId,
                    'bank_account_id' => $ids['bank_account'],
                    'journal_entry_id' => $paymentJournalId,
                    'payment_method' => 'bank',
                    'amount' => $total,
                    'paid_at' => $date,
                    'reference' => 'PERF-'.$number,
                    'idempotency_key' => 'perf-payment-'.$number,
                    'request_hash' => hash('sha256', $number.$total.$date),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            $this->progress($i + 1, $records, 'bills');
        }

        $this->advanceSequence('bill', $sequence + $records);
    }

    private function seedExpenses(int $records, array $ids): void
    {
        $start = DB::table('expenses')->where('company_id', $this->companyId)->count() + 1;

        for ($i = 0; $i < $records; $i++) {
            $date = $this->dateFor($i);
            $amount = $this->amountFor($i, 25);
            $taxed = $i % 3 === 0;
            $taxAmount = $taxed ? $this->percent($amount, 10) : '0.00';
            $cashTotal = $this->add($amount, $taxAmount);
            $key = 'perf-expense-'.$this->companyId.'-'.str_pad((string) ($start + $i), 8, '0', STR_PAD_LEFT);

            $expenseId = DB::table('expenses')->insertGetId([
                'company_id' => $this->companyId,
                'category_id' => $ids['category'],
                'bank_account_id' => $ids['bank_account'],
                'journal_entry_id' => null,
                'tax_rate_id' => $taxed ? $ids['purchase_tax'] : null,
                'date' => $date,
                'description' => 'Performance direct expense',
                'amount' => $amount,
                'tax_amount' => $taxAmount,
                'payment_method' => 'bank',
                'status' => 'posted',
                'idempotency_key' => $key,
                'request_hash' => hash('sha256', $key.$amount.$date),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $journalId = $this->journal($date, 'EXP-'.$expenseId, 'expense', $expenseId);
            $lines = [
                $this->line($journalId, $ids['expenses'], $amount, '0.00'),
                $this->line($journalId, $ids['bank'], '0.00', $cashTotal),
            ];
            if ($taxed) {
                $lines[] = $this->line($journalId, $ids['input_tax'], $taxAmount, '0.00');
            }
            DB::table('journal_lines')->insert($lines);
            DB::table('expenses')->where('id', $expenseId)->update(['journal_entry_id' => $journalId, 'updated_at' => $this->now]);

            $this->progress($i + 1, $records, 'expenses');
        }
    }

    private function ensureContact(string $table, string $name): int
    {
        $id = DB::table($table)->where('company_id', $this->companyId)->where('name', $name)->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId([
            'company_id' => $this->companyId,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function ensureTaxRate(string $name, string $rate, string $type): int
    {
        $id = DB::table('tax_rates')->where('company_id', $this->companyId)->where('name', $name)->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('tax_rates')->insertGetId([
            'company_id' => $this->companyId,
            'name' => $name,
            'rate' => $rate,
            'type' => $type,
            'is_default' => false,
            'is_active' => true,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function account(string $systemKey): int
    {
        return (int) DB::table('accounts')->where('company_id', $this->companyId)->where('system_key', $systemKey)->value('id');
    }

    private function journal(string $date, string $reference, string $sourceType, ?int $sourceId): int
    {
        return (int) DB::table('journal_entries')->insertGetId([
            'company_id' => $this->companyId,
            'date' => $date,
            'reference' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'posted',
            'created_by' => $this->userId,
            'posted_at' => $this->now,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function line(int $journalId, int $accountId, string $debit, string $credit): array
    {
        return [
            'company_id' => $this->companyId,
            'journal_entry_id' => $journalId,
            'account_id' => $accountId,
            'debit' => $debit,
            'credit' => $credit,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ];
    }

    private function nextSequence(string $type): int
    {
        return (int) DB::table('document_sequences')
            ->where('company_id', $this->companyId)
            ->where('type', $type)
            ->value('next_number');
    }

    private function advanceSequence(string $type, int $next): void
    {
        DB::table('document_sequences')
            ->where('company_id', $this->companyId)
            ->where('type', $type)
            ->update(['next_number' => $next, 'updated_at' => $this->now]);
    }

    private function dateFor(int $index): string
    {
        $day = ($index % 365) + 1;

        return date('Y-m-d', strtotime('2026-01-01 +'.($day - 1).' days'));
    }

    private function dueDate(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date.' +'.$days.' days'));
    }

    private function amountFor(int $index, int $base): string
    {
        return number_format($base + (($index * 17) % 875) + (($index % 4) * 0.25), 2, '.', '');
    }

    private function percent(string $amount, int $rate): string
    {
        return number_format(((float) $amount * $rate) / 100, 2, '.', '');
    }

    private function add(string $left, string $right): string
    {
        return number_format((float) $left + (float) $right, 2, '.', '');
    }

    private function progress(int $done, int $total, string $label): void
    {
        if ($done % 1000 === 0 || $done === $total) {
            $this->command?->info("  {$done}/{$total} {$label}");
        }
    }
}
