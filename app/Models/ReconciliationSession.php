<?php

namespace App\Models;

class ReconciliationSession extends TenantModel
{
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
