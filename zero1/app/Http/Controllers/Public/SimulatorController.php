<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BillingCycle;
use App\Models\Subscription;
use App\Services\Payments\GatewayPayment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SimulatedGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tela que imita o checkout do provedor. Existe apenas com PAYMENT_GATEWAY=simulated.
 * Nenhum dado de cartão é solicitado ou armazenado.
 */
class SimulatorController extends Controller
{
    public function __construct(private PaymentGatewayManager $gateways)
    {
        abort_unless($gateways->isSimulated(), 404);
    }

    public function show(Request $request, string $externalId): View
    {
        [$sub, $cycle] = $this->resolve($request, $externalId);

        return view('public.simulator', compact('sub', 'cycle'));
    }

    public function pay(Request $request, string $externalId): RedirectResponse
    {
        [$sub, $cycle] = $this->resolve($request, $externalId);
        $v = $request->validate(['outcome' => ['required', 'in:approved,rejected,pending']]);

        if ($v['outcome'] !== 'pending') {
            /** @var SimulatedGateway $gw */
            $gw = $this->gateways->driver('simulated');
            $gw->simulateCheckout($externalId, $cycle->externalReference(),
                $v['outcome'] === 'approved' ? GatewayPayment::APPROVED : GatewayPayment::REJECTED, $request->boolean('lose_webhook'));
        }

        return redirect()->route('subscription.return');
    }

    private function resolve(Request $request, string $externalId): array
    {
        $sub = Subscription::where('gateway_subscription_id', $externalId)->firstOrFail();
        $user = $request->user();
        abort_unless($sub->customer_id === $user->customer?->id || $user->can('subscriptions.manage'), 404);
        $cycle = BillingCycle::findByReference((string) $request->input('ref')) ?? $sub->latestCycle();
        abort_unless($cycle && $cycle->subscription_id === $sub->id, 404);

        return [$sub, $cycle];
    }
}
