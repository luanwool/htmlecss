<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF segundo o algoritmo oficial (Receita Federal): 11 dígitos, nenhum CPF trivial
 * (00000000000, 11111111111, ...) e os dois dígitos verificadores corretos.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cpf = preg_replace('/\D/', '', (string) $value);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('Informe um CPF válido.');

            return;
        }
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($i = 0; $i < $t; $i++) {
                $d += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $d = (10 * $d) % 11 % 10;
            if ((int) $cpf[$t] !== $d) {
                $fail('Informe um CPF válido.');

                return;
            }
        }
    }

    public static function digits(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $d = preg_replace('/\D/', '', $v);

        return $d === '' ? null : $d;
    }

    /** Formata para exibição completa: 123.456.789-00. */
    public static function format(?string $v): ?string
    {
        $d = self::digits($v);
        if (! $d || strlen($d) !== 11) {
            return $v;
        }

        return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
    }

    /** Mascara para exibição pública/log: 123.***.***-00. */
    public static function mask(?string $v): ?string
    {
        $d = self::digits($v);
        if (! $d || strlen($d) !== 11) {
            return null;
        }

        return substr($d, 0, 3).'.***.***-'.substr($d, 9, 2);
    }
}
