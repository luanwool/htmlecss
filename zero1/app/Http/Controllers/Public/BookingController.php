<?php

namespace App\Http\Controllers\Public;

use App\Enums\SubscriptionStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use App\Services\Booking\BookingPolicy;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Club\EntitlementService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fluxo de agendamento em etapas (server-side, funciona sem JS):
 * serviço → profissional ou "qualquer" → data → horário → confirmação atômica.
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $booking, private EntitlementService $entitlements, private BookingPolicy $policy) {}

    public function create(Request $request): View
    {
        $user = $request->user();
        $customer = $user && ! $user->isStaff() ? $user->customerProfile() : null;
        $subscription = $customer ? $this->entitlements->usableSubscription($customer) : null;
        $planUsable = $subscription && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::CancelRequested], true);
        $covered = $subscription ? $this->entitlements->coveredServiceIds($subscription) : [];

        $services = Service::query()->active()->where('is_public', true)
            ->where(fn ($q) => $q->where('is_bookable_standalone', true)->when($covered, fn ($w) => $w->orWhereIn('id', $covered)))
            ->whereHas('barbers', fn ($q) => $q->active())
            ->orderBy('sort_order')->orderBy('name')->get();

        $service = $services->firstWhere('id', $request->integer('servico'));
        $mode = 'avulso';
        if ($service && $planUsable && in_array($service->id, $covered) && $request->query('modo') !== 'avulso') {
            $mode = 'plan';
        }
        if ($service && $mode === 'avulso' && ! $service->is_bookable_standalone) {
            $service = null; // serviço exclusivo de plano sem plano utilizável
        }

        $planSub = $mode === 'plan' ? $subscription : null;
        $barbers = collect();
        if ($service) {
            $barbers = Barber::query()->active()->where('is_public', true)
                ->whereHas('services', fn ($q) => $q->where('services.id', $service->id))
                ->when($planSub && ! $planSub->term('all_barbers', true), fn ($q) => $q->whereIn('id', $planSub->term('barber_ids', [])))
                ->orderBy('sort_order')->get();
        }
        $barberParam = $request->query('profissional', 'qualquer');
        $barberId = $barberParam === 'qualquer' ? null : (int) $barberParam;
        if ($barberId && ! $barbers->contains('id', $barberId)) {
            $barberId = null;
        }

        $today = BusinessTime::now()->startOfDay();
        $days = collect(range(0, $this->policy->maxAdvanceDays()))->map(fn ($i) => $today->addDays($i));
        $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('data'), BusinessTime::tz())->startOfDay(), null, false);
        if (! $date || $date->lt($today) || $date->gt($days->last())) {
            $date = null;
        }

        $slots = ($service && $date) ? $this->booking->availableSlots($service, $barberId, $date, $planSub) : [];
        $time = $request->query('hora');
        $selected = $time ? collect($slots)->firstWhere('time', $time) : null;
        if (! $user && $selected) {
            // Após login/cadastro, volta direto para a confirmação deste horário.
            session()->put('url.intended', $request->fullUrl().'#confirmar');
        }

        return view('public.booking', compact('services', 'service', 'barbers', 'barberId', 'days', 'date', 'slots', 'selected', 'mode', 'subscription', 'planUsable', 'covered', 'customer'));
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'mode' => ['required', 'in:plan,avulso'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $user = $request->user();
        abort_if($user->isStaff(), 403, 'Use o painel para agendar em nome de clientes.');

        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['time'], BusinessTime::tz());
        $back = route('booking.start', ['servico' => $v['service_id'], 'profissional' => $v['barber_id'] ?? 'qualquer', 'data' => $v['date'], 'modo' => $v['mode']]);

        try {
            $appointment = $this->booking->book(new BookingRequest(
                customer: $user->customerProfile(), service: Service::findOrFail($v['service_id']), barberId: $v['barber_id'] ?? null,
                start: $start, mode: $v['mode'], origin: 'online', actor: $user, notes: $v['notes'] ?? null,
            ));
        } catch (BookingException $e) {
            return redirect($back)->withErrors(['booking' => $e->getMessage()])->with('alternatives', $e->alternatives);
        }

        return redirect()->route('account.appointments.show', $appointment->uid)->with('status', 'Horário confirmado!')->with('just_booked', true);
    }
}
