<?php

namespace App\Models;

class CompanyInvitation extends TenantModel
{
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
