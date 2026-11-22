<?php

namespace App\Http\Controllers\Barber;

use App\Exceptions\BookingException;
use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ServiceVisit;
use App\Services\Booking\BookingService;
use App\Services\Operations\VisitService;
use App\Support\AgendaPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $barber = $request->user()->barber?->load('services', 'photo');
        $period = AgendaPeriod::resolve($request->query('periodo'), $request->query('inicio'));
        $appointments = $barber ? Appointment::with('customer')->where('barber_id', $barber->id)
            ->whereBetween('starts_at', [$period->fromUtc(), $period->toUtc()])
            ->whereNotIn('status', ['cancelled_by_customer', 'cancelled_by_shop'])->orderBy('starts_at')->get() : collect();
        $todayVisits = $barber ? ServiceVisit::with('commission')->where('barber_id', $barber->id)
            ->whereBetween('performed_at', [AgendaPeriod::resolve('hoje')->fromUtc(), AgendaPeriod::resolve('hoje')->toUtc()])->get() : collect();

        return view('barber.dashboard', [
            'barber' => $barber,
            'period' => $period,
            'byDay' => $appointments->groupBy(fn ($a) => $a->localStart()->toDateString()),
            'todayVisits' => $todayVisits,
            'paymentMethods' => ServiceVisit::PAYMENT_METHODS,
            'showCommission' => $request->user()->can('finance.view') || config('zero1.barbers_see_commission', true),
        ]);
    }

    public function action(Request $request, Appointment $appointment, string $action, BookingService $booking, VisitService $visits): RedirectResponse
    {
        // Isolamento: barbeiro só age na PRÓPRIA agenda.
        abort_unless($appointment->barber_id === $request->user()->barber?->id, 403);
        try {
            match ($action) {
                'iniciar' => $booking->start($appointment, $request->user()),
                'ausente' => $booking->markNoShow($appointment, $request->user()),
                'concluir' => $visits->complete($appointment, AgendaController::visitData($request), $request->user()),
            };
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', 'Atendimento atualizado.');
    }
}
