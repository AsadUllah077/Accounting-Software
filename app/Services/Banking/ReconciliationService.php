<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Models\Reconciliation;
use App\Services\Audit\Audit;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReconciliationService
{
    public function import(BankAccount $bank, array $rows): int
    {
        return DB::transaction(function () use ($bank, $rows) {
            $count = 0;
            foreach ($rows as $row) {
                $amount = Decimal::round((string) $row['amount']);
                if (bccomp($amount, '0', 2) === 0) {
                    throw ValidationException::withMessages(['file' => 'Zero-value statement lines are not accepted.']);
                }
                // An explicit statement reference distinguishes otherwise identical legitimate lines.
                $hash = hash('sha256', json_encode([$row['date'], $amount, trim($row['description']), trim($row['reference'] ?? '')]));
                $transaction = BankTransaction::firstOrCreate(['bank_account_id' => $bank->id, 'import_hash' => $hash], ['date' => $row['date'], 'description' => $row['description'], 'reference' => $row['reference'] ?? null, 'amount' => $amount, 'transaction_type' => bccomp($amount, '0', 2) > 0 ? 'receipt' : 'payment', 'imported_at' => now()]);
                if ($transaction->wasRecentlyCreated) {
                    $count++;
                }
            }
            Audit::record('bank.statement_imported', $bank, [], ['imported' => $count]);

            return $count;
        });
    }

    public function match(BankTransaction $transaction, int $lineId): Reconciliation
    {
        return DB::transaction(function () use ($transaction, $lineId) {
            $transaction = BankTransaction::lockForUpdate()->findOrFail($transaction->id);
            $line = JournalLine::with('entry')->lockForUpdate()->findOrFail($lineId);
            $bank = BankAccount::findOrFail($transaction->bank_account_id);
            if (Reconciliation::where('bank_transaction_id', $transaction->id)->exists() || Reconciliation::where('journal_line_id', $line->id)->exists()) {
                $this->fail('The statement transaction or ledger line is already reconciled.');
            }
            if ($line->account_id !== $bank->account_id || $line->entry->status !== 'posted') {
                $this->fail('Select a posted line for the same bank account.');
            }
            $amount = bcsub($line->debit, $line->credit, 2);
            if (bccomp($amount, (string) $transaction->amount, 2) !== 0) {
                $this->fail('The signed statement amount must equal the ledger amount.');
            }
            $record = Reconciliation::create(['bank_transaction_id' => $transaction->id, 'journal_entry_id' => $line->journal_entry_id, 'journal_line_id' => $line->id, 'matched_amount' => $amount, 'reconciled_at' => now()]);
            Audit::record('bank.reconciled', $record, [], ['transaction_id' => $transaction->id, 'journal_line_id' => $line->id]);

            return $record;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['reconciliation' => $message]);
    }
}
