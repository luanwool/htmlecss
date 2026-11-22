<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberWorkSchedule;
use App\Models\BlockedInterval;
use App\Models\ScheduleException;
use App\Services\Audit\AuditLogger;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.schedules.index', [
            'barbers' => Barber::where('status', '!=', 'inactive')->with('schedules')->orderBy('sort_order')->get(),
            'exceptions' => ScheduleException::with('barber')->whereDate('date', '>=', BusinessTime::now()->toDateString())->orderBy('date')->get(),
            'blocks' => BlockedInterval::with('barber')->where('ends_at', '>', now())->orderBy('starts_at')->get(),
        ]);
    }

    /** Escala semanal: até 3 períodos por dia (ex.: 09–12 e 13–19 = almoço 12–13). */
    public function updateWeekly(Request $request, Barber $barber): RedirectResponse
    {
        $request->validate(['days' => ['array'], 'days.*.*.start' => ['nullable', 'date_format:H:i'], 'days.*.*.end' => ['nullable', 'date_format:H:i']]);
        $rows = [];
        foreach (range(1, 7) as $day) {
            $periods = collect($request->input("days.{$day}", []))->filter(fn ($p) => ! empty($p['start']) && ! empty($p['end']))
                ->map(fn ($p) => [$p['start'], $p['end']])->sortBy(0)->values();
            foreach ($periods as $i => [$s, $e]) {
                if ($s >= $e) {
                    throw ValidationException::withMessages(["days.{$day}" => BusinessTime::weekdayName($day).': início deve ser antes do fim.']);
                }
                if ($i > 0 && $s < $periods[$i - 1][1]) {
                    throw ValidationException::withMessages(["days.{$day}" => BusinessTime::weekdayName($day).': períodos sobrepostos.']);
                }
                $rows[] = ['barber_id' => $barber->id, 'weekday' => $day, 'starts_at' => $s.':00', 'ends_at' => $e.':00', 'created_at' => now(), 'updated_at' => now()];
            }
        }
        $before = $barber->schedules->map(fn ($s) => "{$s->weekday} {$s->starts_at}-{$s->ends_at}")->all();
        DB::transaction(function () use ($barber, $rows) {
            BarberWorkSchedule::where('barber_id', $barber->id)->delete();
            BarberWorkSchedule::insert($rows);
        });
        $this->audit->log('schedule.weekly_updated', $barber, ['periods' => $before], ['periods' => collect($rows)->map(fn ($r) => "{$r['weekday']} {$r['starts_at']}-{$r['ends_at']}")->all()]);

        return back()->with('status', 'Escala de '.$barber->display_name.' salva. Agendamentos já existentes não são alterados.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'type' => ['required', 'in:closed,open'],
            'starts_at' => ['nullable', 'required_if:type,open', 'date_format:H:i'],
            'ends_at' => ['nullable', 'required_if:type,open', 'date_format:H:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);
        if ($v['type'] === 'open' && empty($v['barber_id'])) {
            throw ValidationException::withMessages(['barber_id' => 'Abertura extraordinária é definida por profissional.']);
        }
        $e = ScheduleException::create([
            'barber_id' => $v['barber_id'] ?? null, 'date' => $v['date'], 'is_closed' => $v['type'] === 'closed',
            'starts_at' => $v['type'] === 'open' ? $v['starts_at'] : null, 'ends_at' => $v['type'] === 'open' ? $v['ends_at'] : null,
            'reason' => $v['reason'] ?? null, 'created_by' => $request->user()->id,
        ]);
        $this->audit->log('schedule.exception_created', $e, [], $v);

        return back()->with('status', 'Exceção registrada. Confira na agenda se há atendimentos já marcados nessa data.');
    }

    public function destroyException(ScheduleException $exception): RedirectResponse
    {
        $this->audit->log('schedule.exception_deleted', $exception, $exception->toArray());
        $exception->delete();

        return back()->with('status', 'Exceção removida.');
    }

    /** Bloqueio por intervalo (férias, ausência, bloqueio manual). Não sobrepõe atendimentos já confirmados. */
    public function storeBlock(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'], 'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$v['start_date']} {$v['start_time']}", BusinessTime::tz())->utc();
        $end = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$v['end_date']} {$v['end_time']}", BusinessTime::tz())->utc();
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_date' => 'O fim deve ser depois do início.']);
        }
        $conflicts = Appointment::query()->occupying()->whereIn('status', ['confirmed', 'in_progress'])
            ->when($v['barber_id'] ?? null, fn ($q, $id) => $q->where('barber_id', $id))
            ->where('starts_at', '<', $end)->where('occupied_until', '>', $start)->count();
        if ($conflicts && ! $request->boolean('force')) {
            throw ValidationException::withMessages(['start_date' => "Há {$conflicts} atendimento(s) confirmado(s) nesse intervalo. Reagende-os antes ou marque “bloquear mesmo assim” (eles continuarão na agenda)."]);
        }
        $b = BlockedInterval::create(['barber_id' => $v['barber_id'] ?? null, 'starts_at' => $start, 'ends_at' => $end, 'reason' => $v['reason'] ?? null, 'created_by' => $request->user()->id]);
        $this->audit->log('schedule.block_created', $b, [], $v);

        return back()->with('status', 'Bloqueio criado.');
    }

    public function destroyBlock(BlockedInterval $block): RedirectResponse
    {
        $this->audit->log('schedule.block_deleted', $block, $block->toArray());
        $block->delete();

        return back()->with('status', 'Bloqueio removido.');
    }
}
