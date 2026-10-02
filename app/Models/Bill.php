<?php

namespace App\Models;

class Bill extends Document
{
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }
}
