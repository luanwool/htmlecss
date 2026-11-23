<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleChangeStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\ScheduleChangeRequest;
use App\Services\Schedule\ScheduleChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleRequestController extends Controller
{
    public function __construct(private ScheduleChangeService $service) {}

    public function index(Request $request): View
    {
        $status = ScheduleChangeStatus::tryFrom((string) $request->query('status')) ?? ScheduleChangeStatus::Pending;
        $items = ScheduleChangeRequest::with(['barber', 'reviewer'])->where('status', $status->value)->latest()->paginate(30)->withQueryString();

        return view('admin.schedule-requests.index', [
            'items' => $items->getCollection()->map(fn (ScheduleChangeRequest $r) => ['request' => $r, 'conflicts' => $this->service->conflicts($r)]),
            'paginator' => $items,
            'status' => $status,
            'counts' => ScheduleChangeRequest::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function approve(Request $request, ScheduleChangeRequest $scheduleRequest): RedirectResponse
    {
        $note = $request->validate(['review_note' => ['nullable', 'string', 'max:300']])['review_note'] ?? null;
        try {
            $this->service->approve($scheduleRequest, $request->user(), $note);
        } catch (BookingException $e) {
            return back()->withErrors(['request' => $e->getMessage()]);
        }

        return back()->with('status', 'Pedido aprovado e aplicado à agenda.');
    }

    public function reject(Request $request, ScheduleChangeRequest $scheduleRequest): RedirectResponse
    {
        $v = $request->validate(['review_note' => ['required', 'string', 'max:300']],
            ['review_note.required' => 'Escreva o motivo da recusa (ele vai por e-mail ao barbeiro).']);
        try {
            $this->service->reject($scheduleRequest, $request->user(), $v['review_note']);
        } catch (BookingException $e) {
            return back()->withErrors(['request' => $e->getMessage()]);
        }

        return back()->with('status', 'Pedido recusado.');
    }
}
