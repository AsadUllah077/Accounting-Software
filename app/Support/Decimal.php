<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Decimal
{
    public static function round(string $value, int $scale = 2): string
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Invalid decimal amount.']);
        }
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, bccomp($value, '0', 12) < 0 ? '-'.$half : $half, $scale);
    }

    public static function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = bcadd($total, (string) $value, 2);
        }

return $total;
    }
}
