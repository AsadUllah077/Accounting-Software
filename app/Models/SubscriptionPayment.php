<?php

namespace App\Models;

class SubscriptionPayment extends TenantModel
{
    public function invoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
