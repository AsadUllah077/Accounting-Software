<?php

namespace App\Models;

class BankAccount extends TenantModel
{
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
