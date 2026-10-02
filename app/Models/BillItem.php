<?php

namespace App\Models;

class BillItem extends DocumentItem
{
    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
