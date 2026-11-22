<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Banco em UTC; exibição e regras de negócio no fuso da barbearia.
 */
final class BusinessTime
{
    public static function tz(): string
    {
        return config('zero1.timezone', 'America/Sao_Paulo');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::tz());
    }

    public static function local(?CarbonInterface $date): ?CarbonImmutable
    {
        return $date ? CarbonImmutable::instance($date)->setTimezone(self::tz()) : null;
    }

    public static function format(?CarbonInterface $date, string $format = 'd/m/Y H:i'): string
    {
        return $date ? self::local($date)->locale('pt_BR')->translatedFormat($format) : '—';
    }

    public static function weekdayName(int $isoWeekday): string
    {
        return [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira',
            5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'][$isoWeekday];
    }
}
