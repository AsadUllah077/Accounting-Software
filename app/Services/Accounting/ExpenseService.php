<?php

namespace App\Services\Accounting;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Services\Audit\Audit;
use App\Services\Invoicing\DocumentService;
use App\Services\Tenancy\CompanyContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExpenseService
{
    public function create(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            $key = $data['idempotency_key'] ?? null;
            $payload = ['category_id' => (int) $data['category_id'], 'bank_account_id' => (int) $data['bank_account_id'], 'tax_rate_id' => empty($data['tax_rate_id']) ? null : (int) $data['tax_rate_id'], 'date' => $data['date'], 'description' => $data['description'], 'amount' => Decimal::round((string) $data['amount'])];
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            if ($key && ($existing = Expense::where('idempotency_key', $key)->first())) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key already belongs to a different expense.']);
                }

                return $existing;
            }
            $category = ExpenseCategory::with('account')->findOrFail($data['category_id']);
            if ($category->account->type !== 'expense') {
                throw ValidationException::withMessages(['category_id' => 'Expense categories require an expense account.']);
            }
            $bank = BankAccount::findOrFail($data['bank_account_id']);
            if ($bank->currency !== app(CompanyContext::class)->company()->currency) {
                throw ValidationException::withMessages(['bank_account_id' => 'Foreign currency expenses are not enabled.']);
            }
            $tax = empty($data['tax_rate_id']) ? null : TaxRate::findOrFail($data['tax_rate_id']);
            if ($tax && ! $tax->is_active) {
                throw ValidationException::withMessages(['tax_rate_id' => 'Inactive tax rates cannot be used.']);
            }
            $amount = Decimal::round((string) $data['amount']);
            $taxAmount = Decimal::round(bcdiv(bcmul($amount, (string) ($tax?->rate ?? '0'), 8), '100', 8));
            $expense = Expense::create(['category_id' => $category->id, 'bank_account_id' => $bank->id, 'tax_rate_id' => $tax?->id, 'date' => $data['date'], 'description' => $data['description'], 'amount' => $amount, 'tax_amount' => $taxAmount, 'idempotency_key' => $key, 'request_hash' => $key ? $hash : null]);
            $lines = [['account_id' => $category->account_id, 'debit' => $amount], ['account_id' => $bank->account_id, 'credit' => bcadd($amount, $taxAmount, 2)]];
            if (bccomp($taxAmount, '0', 2) > 0) {
                $lines[] = ['account_id' => app(DocumentService::class)->account('input_tax')->id, 'debit' => $taxAmount];
            }
            $entry = app(JournalService::class)->post($data['date'], 'EXP-'.$expense->id, $lines, $userId, 'expense', $expense->id);
            $expense->update(['status' => 'posted', 'journal_entry_id' => $entry->id]);
            Audit::record('expense.posted', $expense);

            return $expense;
        });
    }
}
