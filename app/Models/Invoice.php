<?php

namespace App\Models;

class Invoice extends Document
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }
}
