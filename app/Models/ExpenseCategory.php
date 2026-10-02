<?php

namespace App\Models;

class ExpenseCategory extends TenantModel
{
    public function parent()
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
