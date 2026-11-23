<?php

namespace App\Http\Controllers\Barber;

use App\Enums\ScheduleChangeKind;
use App\Enums\ScheduleChangeStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\ScheduleChangeRequest;
use App\Models\User;
use App\Notifications\ScheduleChangeSubmitted;
use App\Services\Schedule\ScheduleChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Barbeiro vê a própria escala e envia pedidos de alteração que vão para análise do ADM.
 */
class ScheduleController extends Controller
{
    public function __construct(private ScheduleChangeService $service) {}

    public function index(Request $request): View
    {
        $barber = $this->barber($request);

        return view('barber.schedule', [
            'barber' => $barber->load(['schedules', 'user']),
            'requests' => ScheduleChangeRequest::where('barber_id', $barber->id)->with('reviewer')->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $barber = $this->barber($request);
        $kind = ScheduleChangeKind::from($request->validate(['kind' => ['required', Rule::enum(ScheduleChangeKind::class)]])['kind']);
        $payload = match ($kind) {
            ScheduleChangeKind::Weekly => $this->weeklyPayload($request),
            ScheduleChangeKind::Exception => $this->exceptionPayload($request),
            ScheduleChangeKind::Block => $this->blockPayload($request),
        };
        try {
            $req = $this->service->submit($barber, $kind, $payload, $request->input('reason'), $request->user());
        } catch (BookingException $e) {
            return back()->withErrors(['request' => $e->getMessage()])->withInput();
        }

        // Avisa os ADMs com permissão de analisar
        User::query()->role(\App\Enums\Role::Owner->value)->get()
            ->each(fn ($u) => rescue(fn () => $u->notify(new ScheduleChangeSubmitted($req)), report: true));

        return redirect()->route('barber.schedule.index')
            ->with('status', 'Pedido enviado. Você verá a resposta aqui e por e-mail.');
    }

    public function cancel(Request $request, ScheduleChangeRequest $scheduleRequest): RedirectResponse
    {
        abort_unless($scheduleRequest->barber_id === $this->barber($request)->id, 404);
        try {
            $this->service->cancelByBarber($scheduleRequest, $request->user());
        } catch (BookingException $e) {
            return back()->withErrors(['request' => $e->getMessage()]);
        }

        return back()->with('status', 'Pedido cancelado.');
    }

    private function barber(Request $request): \App\Models\Barber
    {
        return $request->user()->barber ?? abort(403, 'Usuário sem perfil de profissional vinculado.');
    }

    private function weeklyPayload(Request $request): array
    {
        $v = $request->validate([
            'weekday' => ['required', 'integer', 'between:1,7'],
            'periods' => ['array'],
            'periods.*.start' => ['nullable', 'date_format:H:i'],
            'periods.*.end' => ['nullable', 'date_format:H:i'],
        ]);

        return ['weekday' => $v['weekday'], 'periods' => collect($v['periods'] ?? [])
            ->filter(fn ($p) => ! empty($p['start']) && ! empty($p['end']))
            ->map(fn ($p) => [$p['start'], $p['end']])->values()->all()];
    }

    private function exceptionPayload(Request $request): array
    {
        $v = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'exception_type' => ['required', 'in:closed,open'],
            'starts_at' => ['nullable', 'required_if:exception_type,open', 'date_format:H:i'],
            'ends_at' => ['nullable', 'required_if:exception_type,open', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        return [
            'date' => $v['date'],
            'closed' => $v['exception_type'] === 'closed',
            'starts_at' => $v['starts_at'] ?? null,
            'ends_at' => $v['ends_at'] ?? null,
            'reason' => $v['reason'] ?? null,
        ];
    }

    private function blockPayload(Request $request): array
    {
        $v = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        return [
            'start' => $v['start_date'].' '.$v['start_time'],
            'end' => $v['end_date'].' '.$v['end_time'],
            'reason' => $v['reason'] ?? null,
        ];
    }
}
