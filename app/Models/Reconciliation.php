<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;

class Reconciliation extends TenantModel
{
    use ImmutableRecord;

    public function transaction()
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
