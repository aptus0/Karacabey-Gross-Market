<?php

namespace App\Support;

final class Money
{
    public static function centsToDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function decimalToCents(string|float|int $amount): int
    {
        $normalized = str_replace(',', '.', (string) $amount);

        return (int) round(((float) $normalized) * 100);
    }
}
