<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\EntitlementLedgerEntry;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Services\Club\EntitlementService;
use App\Services\Club\SubscriptionService;
use App\Services\Payments\GatewayPayment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\SimulatedGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions, private PaymentGatewayManager $gateways) {}

    public function index(Request $request): View
    {
        return view('admin.subscriptions.index', [
            'subs' => Subscription::with(['customer', 'plan'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->query('q').'%')))
                ->latest('id')->paginate(30)->withQueryString(),
            'counts' => Subscription::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
            'simulated' => $this->gateways->isSimulated(),
        ]);
    }

    public function show(Subscription $subscription, EntitlementService $entitlements): View
    {
        $subscription->load(['customer', 'plan', 'cycles']);

        return view('admin.subscriptions.show', [
            's' => $subscription,
            'usage' => $subscription->cycles->mapWithKeys(fn ($c) => [$c->id => $entitlements->summary($c)]),
            'ledger' => EntitlementLedgerEntry::where('subscription_id', $subscription->id)->latest('id')->limit(50)->get(),
            'transactions' => PaymentTransaction::where('subscription_id', $subscription->id)->latest('id')->get(),
            'appointments' => Appointment::where('subscription_id', $subscription->id)->with('barber')->latest('starts_at')->limit(20)->get(),
            'simulated' => $this->gateways->isSimulated() && $subscription->gateway === 'simulated',
        ]);
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->subscriptions->requestCancellation($subscription, $request->user(), $request->input('reason'));

        return back()->with('status', 'Cancelamento registrado.');
    }

    public function reconcile(Subscription $subscription): RedirectResponse
    {
        $results = $this->subscriptions->reconcile($subscription);

        return back()->with('status', 'Conciliação concluída: '.collect($results)->map(fn ($r, $k) => "{$k}: {$r}")->implode(' · '));
    }

    public function tick(): RedirectResponse
    {
        $stats = $this->subscriptions->tick();

        return back()->with('status', "Rotina executada: {$stats['renewed']} renovação(ões), {$stats['past_due']} em atraso, {$stats['ended']} encerrada(s).");
    }

    /** Ações do simulador (somente gateway simulado): cobrança, estorno, chargeback, webhook perdido. */
    public function simulate(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($this->gateways->isSimulated() && $subscription->gateway === 'simulated', 404);
        $v = $request->validate(['action' => ['required', 'in:pay,reject,refund,chargeback'], 'cycle_id' => ['required', 'integer'], 'lose_webhook' => ['nullable', 'boolean']]);
        $cycle = $subscription->cycles()->findOrFail($v['cycle_id']);
        /** @var SimulatedGateway $gw */
        $gw = $this->gateways->driver('simulated');
        $lose = $request->boolean('lose_webhook');

        match ($v['action']) {
            'pay' => $gw->simulateCheckout($subscription->gateway_subscription_id, $cycle->externalReference(), GatewayPayment::APPROVED, $lose),
            'reject' => $gw->simulateCheckout($subscription->gateway_subscription_id, $cycle->externalReference(), GatewayPayment::REJECTED, $lose),
            'refund', 'chargeback' => $cycle->gateway_payment_id
                ? $gw->simulateStatusChange($cycle->gateway_payment_id, $v['action'] === 'refund' ? GatewayPayment::REFUNDED : GatewayPayment::CHARGED_BACK, $lose)
                : null,
        };

        return back()->with('status', 'Simulação enviada'.($lose ? ' (webhook descartado — use "Conciliar" para recuperar).' : '.'));
    }
}
