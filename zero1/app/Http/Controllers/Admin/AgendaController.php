<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BlockedInterval;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceVisit;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Club\EntitlementService;
use App\Services\Operations\VisitService;
use App\Support\AgendaPeriod;
use App\Support\BusinessTime;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(private BookingService $booking, private VisitService $visits) {}

    public function index(Request $request, AvailabilityService $availability): View
    {
        $period = AgendaPeriod::resolve($request->query('periodo'), $request->query('inicio'));
        $view = $request->query('visao') === 'colunas' && $period->key === 'hoje' ? 'colunas' : 'lista';

        $appointments = Appointment::query()->with(['barber', 'customer'])
            ->whereBetween('starts_at', [$period->fromUtc(), $period->toUtc()])
            ->when($request->filled('profissional'), fn ($q) => $q->where('barber_id', $request->integer('profissional')))
            ->when($request->filled('servico'), fn ($q) => $q->where('service_id', $request->integer('servico')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')),
                fn ($q) => $q->whereNotIn('status', [AppointmentStatus::CancelledByCustomer->value, AppointmentStatus::CancelledByShop->value]))
            ->when(in_array($request->query('modalidade'), ['plan', 'avulso'], true), fn ($q) => $q->where('mode', $request->query('modalidade')))
            ->when($request->filled('cliente'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->query('cliente').'%')))
            // Ordem: data/hora e profissional — registros simultâneos NUNCA são agrupados.
            ->orderBy('starts_at')->orderBy('barber_id')->get();

        $barbers = Barber::query()->where('status', '!=', 'inactive')->orderBy('sort_order')->get();

        return view('admin.agenda.index', [
            'period' => $period,
            'view' => $view,
            'appointments' => $appointments,
            'byDay' => $appointments->groupBy(fn ($a) => $a->localStart()->toDateString()),
            'barbers' => $barbers,
            'services' => Service::orderBy('name')->get(['id', 'name']),
            'blocks' => BlockedInterval::with('barber')->where('starts_at', '<', $period->toUtc())->where('ends_at', '>', $period->fromUtc())->get(),
            'windows' => $view === 'colunas' ? $barbers->mapWithKeys(fn ($b) => [$b->id => $availability->windowsFor($b, $period->from)]) : collect(),
        ]);
    }

    public function show(Appointment $appointment, EntitlementService $entitlements): View
    {
        $appointment->load(['barber', 'customer', 'service', 'history.user', 'visit', 'subscription', 'billingCycle']);

        return view('admin.agenda.show', [
            'a' => $appointment,
            'usage' => $appointment->billingCycle ? $entitlements->summary($appointment->billingCycle) : null,
            'paymentMethods' => ServiceVisit::PAYMENT_METHODS,
        ]);
    }

    public function create(Request $request, EntitlementService $entitlements): View
    {
        $customer = $request->filled('cliente_id') ? Customer::find($request->integer('cliente_id')) : null;
        $service = $request->filled('servico') ? Service::active()->find($request->integer('servico')) : null;
        $sub = $customer ? $entitlements->usableSubscription($customer) : null;
        $mode = $request->query('modo') === 'plan' && $sub ? 'plan' : 'avulso';
        $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('data'), BusinessTime::tz())->startOfDay(), null, false)
            ?? BusinessTime::now()->startOfDay();
        $barberId = $request->query('profissional', 'qualquer');
        $slots = $service ? $this->booking->availableSlots($service, $barberId === 'qualquer' ? null : (int) $barberId, $date, $mode === 'plan' ? $sub : null, staff: true) : [];

        return view('admin.agenda.create', [
            'customer' => $customer,
            'customers' => $request->filled('busca') ? Customer::query()->where(fn ($q) => $q->where('name', 'like', '%'.$request->query('busca').'%')
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $request->query('busca')).'%')->orWhere('email', 'like', '%'.$request->query('busca').'%'))->limit(10)->get() : collect(),
            'services' => Service::active()->orderBy('name')->get(),
            'service' => $service,
            'barbers' => Barber::active()->orderBy('sort_order')->get(),
            'barberId' => $barberId,
            'date' => $date,
            'slots' => $slots,
            'sub' => $sub,
            'mode' => $mode,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'new_name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'new_phone' => ['nullable', 'string', 'max:20'],
            'new_email' => ['nullable', 'email', 'max:255'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'mode' => ['required', 'in:plan,avulso'],
            'origin' => ['required', Rule::in(['telefone', 'whatsapp', 'balcao', 'painel'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $customer = isset($v['customer_id']) ? Customer::findOrFail($v['customer_id']) : Customer::create([
            'name' => $v['new_name'], 'phone' => isset($v['new_phone']) ? preg_replace('/\D/', '', $v['new_phone']) : null,
            'email' => $v['new_email'] ?? null, 'origin' => $v['origin'], 'created_by' => $request->user()->id,
        ]);

        try {
            $a = $this->booking->book(new BookingRequest(
                customer: $customer, service: Service::findOrFail($v['service_id']), barberId: $v['barber_id'] ?? null,
                start: CarbonImmutable::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['time'], BusinessTime::tz()),
                mode: $v['mode'], origin: $v['origin'], actor: $request->user(), notes: $v['notes'] ?? null, staff: true,
            ));
        } catch (BookingException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()])->with('alternatives', $e->alternatives);
        }

        return redirect()->route('admin.agenda.show', $a)->with('status', 'Agendamento criado com '.$a->barber->display_name.'.');
    }

    public function editTime(Request $request, Appointment $appointment): View
    {
        $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('data'), BusinessTime::tz())->startOfDay(), null, false)
            ?? $appointment->localStart()->startOfDay();
        $barberId = $request->query('profissional', (string) $appointment->barber_id);
        $slots = $this->booking->availableSlots($appointment->service, $barberId === 'qualquer' ? null : (int) $barberId, $date,
            $appointment->isPlan() ? $appointment->subscription : null, staff: true, ignoreAppointmentId: $appointment->id);

        return view('admin.agenda.reschedule', ['a' => $appointment, 'date' => $date, 'slots' => $slots, 'barberId' => $barberId,
            'barbers' => Barber::active()->whereHas('services', fn ($q) => $q->where('services.id', $appointment->service_id))->get()]);
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        $v = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'barber_id' => ['nullable', 'integer']]);
        try {
            $this->booking->reschedule($appointment, CarbonImmutable::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['time'], BusinessTime::tz()),
                $v['barber_id'] ?? null, $request->user(), staff: true);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('admin.agenda.show', $appointment)->with('status', 'Reagendado.');
    }

    public function action(Request $request, Appointment $appointment, string $action): RedirectResponse
    {
        try {
            match ($action) {
                'iniciar' => $this->booking->start($appointment, $request->user()),
                'ausente' => $this->booking->markNoShow($appointment, $request->user()),
                'cancelar' => $this->booking->cancel($appointment, true, $request->user(), $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']),
                'concluir' => $this->visits->complete($appointment, self::visitData($request), $request->user()),
            };
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', 'Agendamento atualizado.');
    }

    public static function visitData(Request $request): array
    {
        $v = $request->validate([
            'price' => ['nullable', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'discount' => ['nullable', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'payment_method' => ['nullable', Rule::in(array_keys(ServiceVisit::PAYMENT_METHODS))],
            'payment_status' => ['nullable', 'in:paid,pending'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        return [
            'price_cents' => isset($v['price']) ? Money::parse($v['price']) : null,
            'discount_cents' => isset($v['discount']) ? Money::parse($v['discount']) : 0,
            'payment_method' => $v['payment_method'] ?? null,
            'payment_status' => $v['payment_status'] ?? 'paid',
            'notes' => $v['notes'] ?? null,
        ];
    }
}
