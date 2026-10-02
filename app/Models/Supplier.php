<?php

namespace App\Models;

class Supplier extends TenantModel
{
    public function bills()
    {
        return $this->hasMany(Bill::class);
    }
}
