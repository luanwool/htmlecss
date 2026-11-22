<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\View\View;

/**
 * Pontos de entrada dos CTAs "Agendar" e "Assinar agora".
 * Enquanto o motor de agenda (Fase 2) e o checkout Mercado Pago (Fase 3) não estão ativos,
 * a página oferece os canais reais de contato — sem simular reserva ou cobrança.
 */
class BookingEntryController extends Controller
{
    public function booking(SiteSettings $settings): View
    {
        return view('public.booking-entry', [
            'mode' => 'booking',
            'plan' => null,
            'serviceId' => request()->integer('servico') ?: null,
            'barberId' => request()->integer('profissional') ?: null,
        ]);
    }

    public function subscribe(MembershipPlan $plan): View
    {
        abort_unless($plan->is_published, 404);

        return view('public.booking-entry', ['mode' => 'subscribe', 'plan' => $plan, 'serviceId' => null, 'barberId' => null]);
    }
}
