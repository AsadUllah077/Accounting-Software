<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;

class Payment extends TenantModel
{
    use ImmutableRecord;

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
