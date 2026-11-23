<?php

namespace App\Services\Operations;

use App\Enums\AppointmentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BillingCycle;
use App\Models\CommissionLedgerEntry;
use App\Models\FinancialEntry;
use App\Models\ServiceVisit;
use App\Models\Subscription;
use App\Services\Booking\AvailabilityService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Indicadores. Separa: receita de caixa efetivamente recebida, a receber, recorrente (MRR)
 * e produção. Corte coberto por plano conta como produção, não como nova receita.
 */
class DashboardMetrics
{
    /** @return array<string, mixed> */
    public function compute(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $fin = FinancialEntry::query()->whereBetween('occurred_at', [$fromUtc, $toUtc]);
        $sum = fn ($q) => (int) $q->sum('amount_cents');

        $incomePaid = $sum((clone $fin)->where('type', 'income')->where('status', 'paid'));
        $receivable = $sum((clone $fin)->where('type', 'income')->where('status', 'pending'));
        $fees = (int) (clone $fin)->where('type', 'income')->sum('fee_cents');
        $refunds = $sum((clone $fin)->where('type', 'refund'));
        $expenses = $sum((clone $fin)->where('type', 'expense'));
        $subsIncome = $sum((clone $fin)->where('type', 'income')->where('category', 'assinatura')->where('status', 'paid'));
        $walkIncome = $sum((clone $fin)->where('type', 'income')->where('category', 'avulso')->where('status', 'paid'));

        $visits = ServiceVisit::query()->whereBetween('performed_at', [$fromUtc, $toUtc]);
        $avulsoVisits = (clone $visits)->where('mode', 'avulso');
        $avulsoCount = (clone $avulsoVisits)->count();

        $appts = Appointment::query()->whereBetween('starts_at', [$fromUtc, $toUtc]);
        $statusCounts = (clone $appts)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $activeSubs = Subscription::query()->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::CancelRequested->value]);
        $mrr = (int) (clone $activeSubs)->sum('price_cents');

        $startedInPeriod = Subscription::query()->whereNotNull('activated_at')->whereBetween('activated_at', [$fromUtc, $toUtc])->count();
        $cancelledInPeriod = Subscription::query()->whereBetween('cancel_requested_at', [$fromUtc, $toUtc])->count();
        $activeAtStart = max(1, Subscription::query()->whereNotNull('activated_at')->where('activated_at', '<', $fromUtc)
            ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $fromUtc))->count());

        return [
            'income_paid' => $incomePaid,
            'net_estimated' => $incomePaid - $fees - $refunds - $expenses,
            'receivable' => $receivable,
            'fees' => $fees,
            'refunds' => $refunds,
            'expenses' => $expenses,
            'subscription_income' => $subsIncome,
            'walk_in_income' => $walkIncome,
            'subs_active' => (clone $activeSubs)->count(),
            'subs_past_due' => Subscription::where('status', SubscriptionStatus::PastDue->value)->count(),
            'subs_cancelled_period' => $cancelledInPeriod,
            'subs_new_period' => $startedInPeriod,
            'churn_rate' => round($cancelledInPeriod / $activeAtStart * 100, 1),
            'mrr' => $mrr,
            'revenue_by_plan' => BillingCycle::query()->join('subscriptions', 'subscriptions.id', '=', 'billing_cycles.subscription_id')
                ->join('membership_plans', 'membership_plans.id', '=', 'subscriptions.membership_plan_id')
                ->where('billing_cycles.status', 'paid')->whereBetween('billing_cycles.paid_at', [$fromUtc, $toUtc])
                ->groupBy('membership_plans.name')->selectRaw('membership_plans.name as plan, SUM(billing_cycles.amount_cents) as total, COUNT(*) as n')
                ->get(),
            'appointments_total' => (int) $statusCounts->sum(),
            'completed' => (int) ($statusCounts[AppointmentStatus::Completed->value] ?? 0),
            'no_show' => (int) ($statusCounts[AppointmentStatus::NoShow->value] ?? 0),
            'cancelled' => (int) (($statusCounts[AppointmentStatus::CancelledByCustomer->value] ?? 0) + ($statusCounts[AppointmentStatus::CancelledByShop->value] ?? 0)),
            'visits_total' => (clone $visits)->count(),
            'visits_plan' => (clone $visits)->where('mode', 'plan')->count(),
            'visits_avulso' => $avulsoCount,
            'avg_ticket_avulso' => $avulsoCount ? (int) round((clone $avulsoVisits)->avg('final_cents')) : 0,
            'by_barber' => (clone $visits)->join('barbers', 'barbers.id', '=', 'service_visits.barber_id')
                ->groupBy('barbers.display_name')
                ->selectRaw("barbers.display_name as name, COUNT(*) as n, SUM(CASE WHEN mode='plan' THEN 1 ELSE 0 END) as plan, SUM(final_cents) as revenue")
                ->orderByDesc('n')->get(),
            'by_service' => (clone $visits)->groupBy('service_name_snapshot')
                ->selectRaw("service_name_snapshot as name, COUNT(*) as n, SUM(CASE WHEN mode='plan' THEN 1 ELSE 0 END) as plan")
                ->orderByDesc('n')->get(),
            'commission_due' => (int) CommissionLedgerEntry::query()->whereNull('paid_out_at')
                ->whereHas('visit', fn ($q) => $q->whereBetween('performed_at', [$fromUtc, $toUtc]))->sum('amount_cents'),
            'popular_hours' => (clone $appts)->whereIn('status', AppointmentStatus::occupying())->get(['starts_at'])
                ->countBy(fn ($a) => BusinessTime::local($a->starts_at)->format('H:00'))->sortDesc()->take(5),
            'occupancy' => $this->occupancy($fromUtc, $toUtc),
            'series' => $this->series(14),
        ];
    }

    /**
     * Série dos últimos N dias (local): entradas de caixa, atendimentos concluídos e faltas por dia.
     * Usada nos mini-gráficos do painel.
     *
     * @return array{labels: string[], income: int[], visits: int[], no_show: int[]}
     */
    public function series(int $days = 14): array
    {
        $end = \App\Support\BusinessTime::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();
        $tz = config('zero1.timezone');
        $labels = $income = $visits = $noShow = [];
        $buckets = fn () => array_fill(0, $days, 0);
        $byDay = [
            'income' => $buckets(),
            'visits' => $buckets(),
            'noShow' => $buckets(),
        ];
        $index = function (\DateTimeInterface $dt) use ($start, $tz, $days) {
            $local = \Carbon\CarbonImmutable::instance($dt)->setTimezone($tz)->startOfDay();
            $i = (int) $start->diffInDays($local, false);

            return $i >= 0 && $i < $days ? $i : null;
        };
        foreach (\App\Models\FinancialEntry::query()->where('type', 'income')->where('status', 'paid')
            ->whereBetween('occurred_at', [$start->utc(), $end->utc()])->get(['occurred_at', 'amount_cents']) as $e) {
            $i = $index($e->occurred_at);
            if ($i !== null) {
                $byDay['income'][$i] += (int) $e->amount_cents;
            }
        }
        foreach (\App\Models\ServiceVisit::query()
            ->whereBetween('performed_at', [$start->utc(), $end->utc()])->get(['performed_at']) as $v) {
            $i = $index($v->performed_at);
            if ($i !== null) {
                $byDay['visits'][$i]++;
            }
        }
        foreach (\App\Models\Appointment::query()->where('status', 'no_show')
            ->whereBetween('starts_at', [$start->utc(), $end->utc()])->get(['starts_at']) as $a) {
            $i = $index($a->starts_at);
            if ($i !== null) {
                $byDay['noShow'][$i]++;
            }
        }
        for ($i = 0; $i < $days; $i++) {
            $labels[] = $start->addDays($i)->toDateString();
        }

        return ['labels' => $labels, 'income' => $byDay['income'], 'visits' => $byDay['visits'], 'noShow' => $byDay['noShow']];
    }

    /** Ocupação = minutos agendados ÷ minutos de escala, no período (aproximação por barbeiro ativo). */
    private function occupancy(CarbonImmutable $fromUtc, CarbonImmutable $toUtc): ?float
    {
        $availability = app(AvailabilityService::class);
        $barbers = Barber::active()->get();
        $days = min(62, (int) ceil($fromUtc->diffInDays($toUtc)) + 1);
        $capacity = 0;
        foreach ($barbers as $b) {
            for ($i = 0; $i < $days; $i++) {
                foreach ($availability->windowsFor($b, $fromUtc->setTimezone(config('zero1.timezone'))->addDays($i)) as [$s, $e]) {
                    $capacity += $s->diffInMinutes($e);
                }
            }
        }
        if ($capacity === 0) {
            return null;
        }
        $booked = Appointment::query()->whereBetween('starts_at', [$fromUtc, $toUtc])->whereIn('status', AppointmentStatus::occupying())
            ->get(['starts_at', 'occupied_until'])->sum(fn ($a) => $a->starts_at->diffInMinutes($a->occupied_until));

        return round(min(100, $booked / $capacity * 100), 1);
    }
}
