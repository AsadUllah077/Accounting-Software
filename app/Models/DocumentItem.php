<?php

namespace App\Models;

use LogicException;

abstract class DocumentItem extends TenantModel
{
    protected static function booted(): void
    {
        parent::booted();
        $check = function (self $item) {
            $invoice = $item instanceof InvoiceItem;
            $parent = $invoice ? Invoice::findOrFail($item->invoice_id) : Bill::findOrFail($item->bill_id);
            if ($parent->status !== 'draft') {
                throw new LogicException('Posted document items are immutable.');
            }
        };
        static::saving($check);
        static::deleting($check);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
