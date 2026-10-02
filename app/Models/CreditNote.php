<?php

namespace App\Models;

use App\Models\Concerns\ImmutableWhenPosted;

class CreditNote extends TenantModel
{
    use ImmutableWhenPosted;

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function refunds()
    {
        return $this->hasMany(CustomerRefund::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
