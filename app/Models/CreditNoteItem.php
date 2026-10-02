<?php

namespace App\Models;

class CreditNoteItem extends TenantModel
{
    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
