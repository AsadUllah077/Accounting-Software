<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['features' => 'array', 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
