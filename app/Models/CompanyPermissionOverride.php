<?php

namespace App\Models;

class CompanyPermissionOverride extends TenantModel
{
    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
