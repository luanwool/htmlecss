<?php

namespace App\Support;

final class Money
{
    public static function format(?int $cents, bool $withSymbol = true): string
    {
        if ($cents === null) {
            return 'Sob consulta';
        }

        $value = number_format($cents / 100, 2, ',', '.');

        return $withSymbol ? 'R$ '.$value : $value;
    }

    /** Converte "1.234,56", "1234.56" ou "45" em centavos. */
    public static function parse(string|int|float|null $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        if (is_int($input)) {
            return $input * 100;
        }
        $s = preg_replace('/[^\d,.\-]/', '', (string) $input);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return (int) round(((float) $s) * 100);
    }
}
