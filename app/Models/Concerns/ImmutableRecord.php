<?php

namespace App\Models\Concerns;

use LogicException;

trait ImmutableRecord
{
    protected static function bootImmutableRecord(): void
    {
        static::updating(fn () => throw new LogicException('Financial records are immutable. Use the originating adjustment workflow.'));
        static::deleting(fn () => throw new LogicException('Financial records cannot be deleted.'));
    }
}
