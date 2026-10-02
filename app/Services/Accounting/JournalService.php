<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JournalService
{
    public function post(string $date, string $reference, array $lines, int $userId, ?string $sourceType = null, ?int $sourceId = null, ?int $reversalOf = null): JournalEntry
    {
        return DB::transaction(function () use ($date, $reference, $lines, $userId, $sourceType, $sourceId, $reversalOf) {
            $company = app(CompanyContext::class)->company();
            // Serializes posting against period locks and financial year closure.
            $company = $company->newQuery()->lockForUpdate()->findOrFail($company->id);
            if (($company->locked_through && $date <= $company->locked_through->toDateString()) || DB::table('financial_years')->where('company_id', $company->id)->where('is_closed', true)->where('start_date', '<=', $date)->where('end_date', '>=', $date)->exists()) {
                $this->fail('This accounting period is locked.');
            }
            if (count($lines) < 2) {
                $this->fail('At least two journal lines are required.');
            }
            foreach ($lines as &$line) {
                $account = Account::findOrFail($line['account_id']);
                if (! $account->is_active) {
                    $this->fail('Inactive accounts cannot receive postings.');
                }
                $line['debit'] = Decimal::round((string) ($line['debit'] ?? '0'));
                $line['credit'] = Decimal::round((string) ($line['credit'] ?? '0'));
                if (bccomp($line['debit'], '0', 2) < 0 || bccomp($line['credit'], '0', 2) < 0 || (bccomp($line['debit'], '0', 2) === 0) === (bccomp($line['credit'], '0', 2) === 0)) {
                    $this->fail('Each line requires exactly one positive debit or credit.');
                }
            }
            unset($line);
            if (bccomp(Decimal::sum(array_column($lines, 'debit')), Decimal::sum(array_column($lines, 'credit')), 2) !== 0) {
                $this->fail('Journal debits and credits must balance.');
            }
            $entry = JournalEntry::create(['date' => $date, 'reference' => $reference, 'created_by' => $userId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'reversal_of_id' => $reversalOf]);
            $entry->lines()->createMany($lines);
            $entry->update(['status' => 'posted', 'posted_at' => now()]);
            Audit::record('journal.posted', $entry, [], ['reference' => $reference], $userId);

            return $entry->load('lines.account');
        });
    }

    public function reverse(JournalEntry $entry, string $date, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $userId) {
            $entry = JournalEntry::lockForUpdate()->findOrFail($entry->id);
            if ($entry->status !== 'posted' || JournalEntry::where('reversal_of_id', $entry->id)->exists()) {
                $this->fail('Only an unreversed posted journal can be reversed.');
            }
            if ($entry->source_type !== null) {
                $this->fail('Use the originating document adjustment workflow for automatic journals.');
            }
            if ($date < $entry->date->toDateString()) {
                $this->fail('Reversal date cannot precede the original posting.');
            }
            $lines = $entry->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit])->all();
            $reversal = $this->post($date, 'REV-'.$entry->id, $lines, $userId, null, null, $entry->id);
            Audit::record('journal.reversed', $entry, [], ['reversal_id' => $reversal->id]);

            return $reversal;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['journal' => $message]);
    }
}
