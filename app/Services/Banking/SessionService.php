<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Models\Reconciliation;
use App\Models\ReconciliationSession;
use App\Services\Audit\Audit;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SessionService
{
    public function open(array $data): ReconciliationSession
    {
        return DB::transaction(function () use ($data) {
            BankAccount::lockForUpdate()->findOrFail($data['bank_account_id']);
            $session = ReconciliationSession::create($data);
            Audit::record('reconciliation.session_opened', $session);

            return $session;
        });
    }

    public function close(ReconciliationSession $session): void
    {
        DB::transaction(function () use ($session) {
            $session = ReconciliationSession::lockForUpdate()->findOrFail($session->id);
            $bank = BankAccount::lockForUpdate()->findOrFail($session->bank_account_id);
            if ($session->status !== 'open') {
                $this->fail('This session is already closed.');
            }
            if (BankTransaction::where('bank_account_id', $bank->id)->where('date', '<=', $session->statement_date)->whereNotIn('id', Reconciliation::select('bank_transaction_id'))->exists()) {
                $this->fail('All statement transactions through this date must be matched.');
            }
            $balance = Decimal::sum(JournalLine::where('account_id', $bank->account_id)->whereHas('entry', fn ($q) => $q->where('status', 'posted')->where('date', '<=', $session->statement_date))->get()->map(fn ($line) => bcsub($line->debit, $line->credit, 2)));
            if (bccomp($balance, (string) $session->closing_balance, 2) !== 0) {
                $this->fail('The ledger bank balance does not match the statement closing balance.');
            }
            $session->update(['status' => 'closed', 'closed_at' => now()]);
            Audit::record('reconciliation.session_closed', $session, [], ['closing_balance' => $balance]);
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['session' => $message]);
    }
}
