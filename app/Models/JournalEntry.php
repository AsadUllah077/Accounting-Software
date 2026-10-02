<?php

namespace App\Models;

use Carbon\Carbon;
use LogicException;

class JournalEntry extends TenantModel
{
    public function setDateAttribute($value): void
    {
        // Store calendar dates consistently, including SQLite development tests.
        $this->attributes['date'] = Carbon::parse($value)->toDateString();
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        parent::booted();
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') === 'posted') {
                throw new LogicException('Posted entries are immutable.');
            }
        });
        static::deleting(function (self $entry) {
            if ($entry->status === 'posted') {
                throw new LogicException('Posted entries cannot be deleted.');
            }
        });
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
