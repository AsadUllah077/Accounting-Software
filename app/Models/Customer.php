<?php

namespace App\Models;

class Customer extends TenantModel
{
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
