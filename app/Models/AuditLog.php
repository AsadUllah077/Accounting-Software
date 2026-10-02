<?php

namespace App\Models;

use App\Models\Concerns\ImmutableRecord;

class AuditLog extends TenantModel
{
    use ImmutableRecord;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
