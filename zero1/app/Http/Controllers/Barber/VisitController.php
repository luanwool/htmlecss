<?php

namespace App\Http\Controllers\Barber;

use App\Http\Controllers\Admin\VisitController as BaseVisitController;
use App\Models\Barber;
use App\Models\ServiceVisit;
use App\Support\AgendaPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Barbeiro registra e vê SOMENTE os próprios atendimentos. */
class VisitController extends BaseVisitController
{
    public function index(Request $request): View
    {
        $barber = $this->barber($request);
        $period = AgendaPeriod::resolve($request->query('periodo', 'hoje'), $request->query('inicio'));
        $visits = ServiceVisit::with(['customer', 'commission'])->where('barber_id', $barber->id)
            ->whereBetween('performed_at', [$period->fromUtc(), $period->toUtc()])->latest('performed_at')->get();

        return view('barber.visits', compact('barber', 'period', 'visits'));
    }

    public function create(Request $request): View
    {
        return view('barber.visit-create', $this->formData($request, $this->barber($request)));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->register($request, $this->barber($request)->id, route('barber.dashboard'));
    }

    private function barber(Request $request): Barber
    {
        return $request->user()->barber ?? abort(403, 'Usuário sem perfil de profissional vinculado.');
    }
}
