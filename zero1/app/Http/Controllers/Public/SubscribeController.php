<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\Page;
use App\Services\Club\SubscriptionService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscribeController extends Controller
{
    public function create(Request $request, MembershipPlan $plan, PaymentGatewayManager $gateways): View
    {
        abort_unless($plan->is_published, 404);
        abort_if($request->user()->isStaff(), 403, 'Contas da equipe não assinam planos.');

        return view('public.subscribe', [
            'plan' => $plan->load('services'),
            'existing' => $request->user()->customerProfile()->liveSubscription(),
            'automaticPix' => $gateways->driver()->supportsAutomaticPix(),
            'simulated' => $gateways->isSimulated(),
            'termsPage' => Page::published()->where('slug', 'termos-de-assinatura')->first(),
        ]);
    }

    public function store(Request $request, MembershipPlan $plan, SubscriptionService $subscriptions): RedirectResponse
    {
        abort_unless($plan->is_published, 404);
        abort_if($request->user()->isStaff(), 403);
        $v = $request->validate(['method' => ['required', 'in:card,pix'], 'accept_terms' => ['accepted']],
            ['accept_terms.accepted' => 'É preciso aceitar os termos do plano.']);

        try {
            $sub = $subscriptions->start($request->user()->customerProfile(), $plan, $v['method']);
        } catch (BookingException $e) {
            return back()->withErrors(['subscription' => $e->getMessage()]);
        }

        // Checkout do provedor (aqui, o simulador). A volta para o site NÃO confirma pagamento.
        return redirect()->away($sub->checkout_url);
    }

    public function returned(Request $request): View
    {
        return view('public.subscription-return', ['subscription' => $request->user()->customerProfile()->subscriptions()->latest('id')->first()]);
    }
}
