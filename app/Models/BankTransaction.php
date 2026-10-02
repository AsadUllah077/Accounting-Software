<?php

namespace App\Models;

class BankTransaction extends TenantModel
{
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
