<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Club\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EntitlementService $entitlements): View
    {
        $user = $request->user();
        $customer = $user->customerProfile();
        $sub = $entitlements->usableSubscription($customer);
        $cycle = $sub?->currentPaidCycle();

        return view('account.dashboard', [
            'user' => $user,
            'upcoming' => $customer->appointments()->with('barber')->upcoming()->orderBy('starts_at')->limit(5)->get(),
            'sub' => $sub,
            'usage' => $cycle ? $entitlements->summary($cycle) : null,
            'cycle' => $cycle,
        ]);
    }
}
