<?php

namespace App\Models\Concerns;

use LogicException;

trait ImmutableWhenPosted
{
    protected static function bootImmutableWhenPosted(): void
    {
        static::updating(function ($record) {
            if ($record->getOriginal('status') === 'posted') {
                throw new LogicException('Posted records are immutable.');
            }
        });
        static::deleting(function ($record) {
            if ($record->status === 'posted') {
                throw new LogicException('Posted records cannot be deleted.');
            }
        });
    }
}
