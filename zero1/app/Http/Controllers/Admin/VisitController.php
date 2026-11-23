<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceVisit;
use App\Services\Operations\VisitService;
use App\Support\AgendaPeriod;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Atendimentos (produção). Compartilhado com o painel do barbeiro, que vê só os próprios. */
class VisitController extends Controller
{
    public function __construct(protected VisitService $visits) {}

    public function index(Request $request): View
    {
        $period = AgendaPeriod::resolve($request->query('periodo', 'mes'), $request->query('inicio'));

        return view('admin.visits.index', [
            'period' => $period,
            'visits' => ServiceVisit::with(['barber', 'customer', 'commission'])
                ->whereBetween('performed_at', [$period->fromUtc(), $period->toUtc()])
                ->when($request->filled('profissional'), fn ($q) => $q->where('barber_id', $request->integer('profissional')))
                ->when(in_array($request->query('modalidade'), ['plan', 'avulso'], true), fn ($q) => $q->where('mode', $request->query('modalidade')))
                ->latest('performed_at')->paginate(50)->withQueryString(),
            'barbers' => Barber::orderBy('display_name')->get(),
            'paymentMethods' => ServiceVisit::PAYMENT_METHODS,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.visits.create', $this->formData($request, null));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->register($request, $request->integer('barber_id'), route('admin.visits.index'));
    }

    public function markPaid(Request $request, ServiceVisit $visit): RedirectResponse
    {
        $v = $request->validate(['payment_method' => ['required', Rule::in(array_keys(ServiceVisit::PAYMENT_METHODS))]]);
        $this->visits->markPaid($visit, $v['payment_method']);

        return back()->with('status', 'Pagamento registrado.');
    }

    protected function formData(Request $request, ?Barber $fixedBarber): array
    {
        return [
            'fixedBarber' => $fixedBarber,
            'barbers' => Barber::active()->orderBy('sort_order')->get(),
            'services' => Service::active()->orderBy('name')->get(),
            'customers' => $request->filled('busca') ? Customer::query()->where(fn ($q) => $q->where('name', 'like', '%'.$request->query('busca').'%')
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', (string) $request->query('busca')).'%'))->limit(10)->get() : collect(),
            'customer' => $request->filled('cliente_id') ? Customer::find($request->integer('cliente_id')) : null,
            'paymentMethods' => ServiceVisit::PAYMENT_METHODS,
            'origins' => ServiceVisit::ORIGINS,
            'now' => BusinessTime::now(),
        ];
    }

    protected function register(Request $request, int $barberId, string $redirect): RedirectResponse
    {
        $v = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'new_name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'new_phone' => ['nullable', 'string', 'max:20'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'mode' => ['required', 'in:plan,avulso'],
            'origin' => ['required', Rule::in(array_keys(ServiceVisit::ORIGINS))],
        ]);
        if (empty($v['customer_id']) && ! $request->boolean('consent')) {
            return back()->withInput()->withErrors(['consent' => 'Confirme que o cliente autorizou o cadastro dos dados.']);
        }
        $data = AgendaController::visitData($request) + ['origin' => $v['origin']];
        if ($v['mode'] === 'avulso' && empty($data['payment_method'])) {
            return back()->withInput()->withErrors(['payment_method' => 'Informe a forma de pagamento do avulso.']);
        }

        $customer = isset($v['customer_id']) ? Customer::findOrFail($v['customer_id']) : Customer::create([
            'name' => $v['new_name'], 'phone' => isset($v['new_phone']) ? preg_replace('/\D/', '', $v['new_phone']) : null,
            'origin' => $v['origin'], 'privacy_accepted_at' => now(), 'created_by' => $request->user()->id,
        ]);

        try {
            $this->visits->walkIn($customer, Service::findOrFail($v['service_id']), $barberId,
                CarbonImmutable::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['time'], BusinessTime::tz()), $v['mode'], $data, $request->user());
        } catch (BookingException $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect($redirect)->with('status', 'Atendimento registrado.');
    }
}
