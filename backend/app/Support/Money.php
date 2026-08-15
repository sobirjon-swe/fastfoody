<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money arithmetic on integer tiyin (1/100 soʻm).
 *
 * Decimal columns come back as strings such as "32000.00"; parsing them as
 * floats would eventually produce 91000.00000000001, so the string is split
 * instead and every sum is done in whole tiyin. It also keeps the code free of
 * the bcmath extension, which is not present everywhere.
 */
final class Money
{
    /**
     * "32000.75" -> 3200075
     */
    public static function toTiyin(string|int|float $amount): int
    {
        $value = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Notoʻgʻri pul qiymati: {$value}");
        }

        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');

        $tiyin = (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$tiyin : $tiyin;
    }

    /**
     * 3200075 -> "32000.75"
     */
    public static function toDecimal(int $tiyin): string
    {
        $sign = $tiyin < 0 ? '-' : '';
        $tiyin = abs($tiyin);

        return $sign.intdiv($tiyin, 100).'.'.str_pad((string) ($tiyin % 100), 2, '0', STR_PAD_LEFT);
    }
}
