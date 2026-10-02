<?php

namespace App\Models;

class Product extends TenantModel
{
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
