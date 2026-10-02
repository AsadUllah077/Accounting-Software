<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;

class CustomerRefund extends TenantModel
{
    use ImmutableRecord;

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
