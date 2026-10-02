<?php

namespace App\Models;

class ReportExport extends TenantModel
{
    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
