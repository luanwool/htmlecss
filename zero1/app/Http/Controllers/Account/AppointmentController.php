<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Booking\BookingService;
use App\Services\Calendar\CalendarEvent;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private BookingService $booking) {}

    public function index(Request $request): View
    {
        $customer = $request->user()->customerProfile();

        return view('account.appointments', [
            'upcoming' => $customer->appointments()->with('barber')->upcoming()->orderBy('starts_at')->get(),
            'past' => $customer->appointments()->with('barber')->where(fn ($q) => $q->where('starts_at', '<', now()->subHour())
                ->orWhereNotIn('status', ['confirmed', 'in_progress']))->latest('starts_at')->limit(30)->get(),
        ]);
    }

    public function show(Request $request, string $uid, CalendarEvent $calendar): View
    {
        $a = $this->owned($request, $uid);

        return view('account.appointment', ['a' => $a->load('barber', 'history'), 'googleUrl' => $calendar->googleUrl($a)]);
    }

    public function ics(Request $request, string $uid, CalendarEvent $calendar): Response
    {
        $a = $this->owned($request, $uid);

        return response($calendar->ics($a), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="zero1-'.$a->localStart()->format('Y-m-d-Hi').'.ics"',
        ]);
    }

    public function editTime(Request $request, string $uid): View
    {
        $a = $this->owned($request, $uid);
        abort_unless($a->status->isOpen(), 404);
        $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('data'), BusinessTime::tz())->startOfDay(), null, false)
            ?? $a->localStart()->startOfDay();
        $barberId = $request->query('profissional', (string) $a->barber_id);
        $slots = $this->booking->availableSlots($a->service, $barberId === 'qualquer' ? null : (int) $barberId, $date,
            $a->isPlan() ? $a->subscription : null, ignoreAppointmentId: $a->id);

        return view('account.reschedule', compact('a', 'date', 'slots', 'barberId'));
    }

    public function reschedule(Request $request, string $uid): RedirectResponse
    {
        $a = $this->owned($request, $uid);
        $v = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'], 'barber_id' => ['nullable', 'integer']]);
        try {
            $this->booking->reschedule($a, CarbonImmutable::createFromFormat('Y-m-d H:i', $v['date'].' '.$v['time'], BusinessTime::tz()), $v['barber_id'] ?? null, $request->user());
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('account.appointments.show', $a->uid)->with('status', 'Horário alterado. Atualize o evento no seu calendário.');
    }

    public function cancel(Request $request, string $uid): RedirectResponse
    {
        $a = $this->owned($request, $uid);
        try {
            $this->booking->cancel($a, false, $request->user(), $request->input('reason'));
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('account.appointments.show', $a->uid)->with('status', 'Agendamento cancelado.');
    }

    /** O cliente só acessa os próprios agendamentos (checagem no backend). */
    private function owned(Request $request, string $uid): Appointment
    {
        $a = Appointment::query()->where('uid', $uid)->firstOrFail();
        abort_unless($a->customer_id === $request->user()->customer?->id, 404);

        return $a;
    }
}
