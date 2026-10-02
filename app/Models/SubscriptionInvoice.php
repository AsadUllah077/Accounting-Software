<?php

namespace App\Models;

class SubscriptionInvoice extends TenantModel
{
    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }
}
