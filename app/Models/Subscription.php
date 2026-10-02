<?php

namespace App\Models;

class Subscription extends TenantModel
{
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
