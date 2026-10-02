<?php

namespace App\Models;

class InvoiceItem extends DocumentItem
{
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
