<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CycleStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\FinancialEntry;
use App\Models\ServiceVisit;
use App\Models\Subscription;
use App\Services\Audit\AuditLogger;
use App\Services\Club\EntitlementService;
use App\Services\Operations\DashboardMetrics;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const RANGES = ['hoje' => 'Hoje', '7' => '7 dias', '30' => '30 dias', 'mes' => 'Mês atual', 'custom' => 'Personalizado'];

    public static function range(Request $request): array
    {
        $now = BusinessTime::now();
        $key = array_key_exists((string) $request->query('periodo'), self::RANGES) ? (string) $request->query('periodo') : 'mes';
        [$from, $to] = match ($key) {
            'hoje' => [$now->startOfDay(), $now->endOfDay()],
            '7' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'custom' => [
                rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('de'), BusinessTime::tz())->startOfDay(), $now->startOfMonth(), false),
                rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('ate'), BusinessTime::tz())->endOfDay(), $now->endOfDay(), false),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return [$key, $from, $to];
    }

    public function index(Request $request, DashboardMetrics $metrics): View
    {
        [$key, $from, $to] = self::range($request);

        return view('admin.reports.index', ['key' => $key, 'from' => $from, 'to' => $to, 'm' => $metrics->compute($from->utc(), $to->utc())]);
    }

    /** Uso dos planos por ciclo vigente: permitidos, reservados, concluídos, saldo e sinais de uso irregular. */
    public function plans(EntitlementService $entitlements): View
    {
        $rows = Subscription::with(['customer', 'plan', 'cycles'])->live()->get()->map(function (Subscription $s) use ($entitlements) {
            $cycle = $s->currentPaidCycle();
            $u = $cycle ? $entitlements->summary($cycle) : null;
            $noShows = Appointment::where('subscription_id', $s->id)->where('status', 'no_show')->where('starts_at', '>', now()->subDays(90))->count();
            $flags = [];
            if ($noShows >= 2) {
                $flags[] = "{$noShows} faltas em 90 dias";
            }
            if ($u && $u['allowance'] !== null && $u['allowance'] < $u['reserved'] + $u['consumed']) {
                $flags[] = 'Uso acima da franquia';
            }
            if ($s->fraud_blocked) {
                $flags[] = 'Bloqueada por contestação';
            }

            return ['s' => $s, 'cycle' => $cycle, 'u' => $u, 'flags' => $flags];
        });

        return view('admin.reports.plans', ['rows' => $rows]);
    }

    public function export(Request $request, string $dataset, AuditLogger $audit): StreamedResponse
    {
        [, $from, $to] = self::range($request);
        [$fromUtc, $toUtc] = [$from->utc(), $to->utc()];
        $audit->log('reports.exported', null, [], ['dataset' => $dataset, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $fmt = fn ($dt) => $dt ? BusinessTime::format($dt, 'Y-m-d H:i') : '';
        $money = fn ($c) => number_format(((int) $c) / 100, 2, ',', '');

        [$header, $rows] = match ($dataset) {
            'atendimentos' => [
                ['data', 'profissional', 'cliente', 'servico', 'modalidade', 'preco', 'desconto', 'final', 'pagamento', 'status_pagamento', 'origem', 'comissao'],
                ServiceVisit::with(['barber', 'customer', 'commission'])->whereBetween('performed_at', [$fromUtc, $toUtc])->orderBy('performed_at')->cursor()
                    ->map(fn ($v) => [$fmt($v->performed_at), $v->barber->display_name, $v->customer->name, $v->service_name_snapshot, $v->mode,
                        $money($v->price_cents), $money($v->discount_cents), $money($v->final_cents), $v->payment_method, $v->payment_status, $v->origin, $money($v->commission?->amount_cents)]),
            ],
            'financeiro' => [
                ['data', 'tipo', 'categoria', 'valor_bruto', 'taxa', 'forma', 'status', 'descricao'],
                FinancialEntry::whereBetween('occurred_at', [$fromUtc, $toUtc])->orderBy('occurred_at')->cursor()
                    ->map(fn ($e) => [$fmt($e->occurred_at), $e->type, $e->category, $money($e->amount_cents), $money($e->fee_cents), $e->payment_method, $e->status, $e->description]),
            ],
            'agendamentos' => [
                ['inicio', 'fim', 'profissional', 'cliente', 'servico', 'duracao_min', 'modalidade', 'status', 'origem'],
                Appointment::with(['barber', 'customer'])->whereBetween('starts_at', [$fromUtc, $toUtc])->orderBy('starts_at')->orderBy('barber_id')->cursor()
                    ->map(fn ($a) => [$fmt($a->starts_at), $fmt($a->ends_at), $a->barber->display_name, $a->customer->name, $a->service_name_snapshot,
                        $a->duration_minutes_snapshot, $a->mode, $a->status->label(), $a->origin]),
            ],
            'assinaturas' => [
                ['cliente', 'plano', 'versao', 'status', 'metodo', 'valor', 'ciclos_pagos', 'inicio', 'cancelamento_efetivo'],
                Subscription::with(['customer', 'plan'])->withCount(['cycles as paid_cycles' => fn ($q) => $q->where('status', CycleStatus::Paid->value)])->cursor()
                    ->map(fn ($s) => [$s->customer->name, $s->term('name'), $s->plan_version, $s->status->label(), $s->payment_method, $money($s->price_cents),
                        $s->paid_cycles, $fmt($s->activated_at), $s->cancel_effective_on?->format('Y-m-d')]),
            ],
        };

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel abrir acentos corretamente
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                // Neutraliza injeção de fórmulas em planilhas.
                fputcsv($out, array_map(fn ($c) => is_string($c) && preg_match('/^[=+\-@]/', $c) ? "'".$c : $c, $row), ';');
            }
            fclose($out);
        }, "zero1-{$dataset}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
