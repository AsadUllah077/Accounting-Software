<?php

namespace App\Models;

use LogicException;

abstract class Document extends TenantModel
{
    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'paid_total' => 'decimal:2', 'credited_total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $doc) {
            if ($doc->getOriginal('status') !== 'draft' && $doc->isDirty(['subtotal', 'tax_total', 'total', 'currency', 'customer_id', 'supplier_id', 'issue_date', 'bill_date', 'due_date', 'notes'])) {
                throw new LogicException('Posted document amounts and details are immutable.');
            }
        });
        static::deleting(function (self $doc) {
            if ($doc->status !== 'draft') {
                throw new LogicException('Posted documents cannot be deleted.');
            }
        });
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function getOutstandingAttribute(): string
    {
        return bcsub(bcsub($this->total, $this->paid_total, 2), $this->credited_total, 2);
    }

    protected $appends = ['outstanding'];
}
