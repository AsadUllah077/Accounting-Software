<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;

class BillPayment extends TenantModel
{
    use ImmutableRecord;

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
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
