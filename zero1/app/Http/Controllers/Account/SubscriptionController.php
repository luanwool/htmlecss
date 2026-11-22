<?php

namespace App\Http\Controllers\Account;

use App\Enums\CycleStatus;
use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use App\Services\Club\EntitlementService;
use App\Services\Club\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(Request $request, EntitlementService $entitlements): View
    {
        $customer = $request->user()->customerProfile();
        $sub = $customer->subscriptions()->with('plan', 'cycles')->latest('id')->first();
        $current = $sub?->currentPaidCycle();

        return view('account.subscription', [
            'sub' => $sub,
            'current' => $current,
            'usage' => $current ? $entitlements->summary($current) : null,
            'payable' => $sub?->cycles->first(fn ($c) => in_array($c->status, [CycleStatus::Pending, CycleStatus::Failed], true)),
        ]);
    }

    public function cancel(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted'], 'reason' => ['nullable', 'string', 'max:255']]);
        $sub = $request->user()->customerProfile()->liveSubscription();
        abort_unless($sub, 404);
        $subscriptions->requestCancellation($sub, $request->user(), $request->input('reason'));

        return back()->with('status', 'Cancelamento registrado. A renovação foi interrompida.');
    }

    /** Pagar mensalidade pendente (Pix por cobrança) — leva ao checkout do provedor. */
    public function pay(Request $request, BillingCycle $cycle): RedirectResponse
    {
        $sub = $cycle->subscription;
        abort_unless($sub->customer_id === $request->user()->customer?->id, 404);
        abort_unless(in_array($cycle->status, [CycleStatus::Pending, CycleStatus::Failed], true), 404);

        return redirect()->route('simulator.checkout', [$sub->gateway_subscription_id, 'ref' => $cycle->externalReference()]);
    }
}
