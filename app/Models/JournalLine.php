<?php

namespace App\Models;

use LogicException;

class JournalLine extends TenantModel
{
    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        parent::booted();
        $check = function (self $line) {
            if ($line->entry()->where('status', 'posted')->exists()) {
                throw new LogicException('Posted journal lines are immutable.');
            }
        };
        static::saving($check);
        static::deleting($check);
    }

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
