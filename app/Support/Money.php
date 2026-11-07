<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function roundHalfUp(string $amount, int $scale = 2): string
    {
        if ($scale < 0 || ! preg_match('/^-?\d+(?:\.\d+)?$/', trim($amount))) {
            throw new InvalidArgumentException('Money must be a plain decimal value and use a nonnegative scale.');
        }

        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');
        $magnitude = $negative ? substr($amount, 1) : $amount;
        [$integer, $fraction] = array_pad(explode('.', $magnitude, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $rounded = $integer.($scale > 0 ? '.'.substr($fraction, 0, $scale) : '');
        $rounded = bcadd($rounded, '0', $scale);

        if ((int) $fraction[$scale] >= 5) {
            $increment = $scale === 0 ? '1' : '0.'.str_repeat('0', $scale - 1).'1';
            $rounded = bcadd($rounded, $increment, $scale);
        }

        return $negative && bccomp($rounded, '0', $scale) !== 0
            ? bcsub('0', $rounded, $scale)
            : $rounded;
    }

    public static function format(string $amount, int $scale = 2, bool $trimZeros = true): string
    {
        $rounded = self::roundHalfUp($amount, $scale);
        $negative = str_starts_with($rounded, '-');
        $magnitude = $negative ? substr($rounded, 1) : $rounded;
        [$integer, $fraction] = array_pad(explode('.', $magnitude, 2), 2, '');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer);

        if ($trimZeros) {
            $fraction = rtrim($fraction, '0');
        }

        $formatted = $integer.($fraction !== '' ? '.'.$fraction : '');

        return $negative ? '-'.$formatted : $formatted;
    }
}
