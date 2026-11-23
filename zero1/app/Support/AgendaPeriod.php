<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Períodos obrigatórios da agenda: hoje, 3, 7, 15 dias e mês (no fuso da barbearia). */
final class AgendaPeriod
{
    public const OPTIONS = ['hoje' => 'Hoje', '3' => '3 dias', '7' => '7 dias', '15' => '15 dias', 'mes' => 'Mês'];

    public function __construct(public string $key, public CarbonImmutable $from, public CarbonImmutable $to) {}

    public static function resolve(?string $key, ?string $startDate = null): self
    {
        $key = array_key_exists((string) $key, self::OPTIONS) ? (string) $key : 'hoje';
        $start = rescue(fn () => $startDate ? CarbonImmutable::createFromFormat('Y-m-d', $startDate, BusinessTime::tz())->startOfDay() : null, null, false)
            ?? BusinessTime::now()->startOfDay();

        [$from, $to] = match ($key) {
            'mes' => [$start->startOfMonth(), $start->startOfMonth()->addMonth()],
            'hoje' => [$start, $start->addDay()],
            default => [$start, $start->addDays((int) $key)],
        };

        return new self($key, $from, $to);
    }

    public function fromUtc(): CarbonImmutable
    {
        return $this->from->utc();
    }

    public function toUtc(): CarbonImmutable
    {
        return $this->to->utc();
    }
}
