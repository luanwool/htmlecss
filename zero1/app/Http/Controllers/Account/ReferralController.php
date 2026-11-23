<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ReferralCredit;
use App\Services\Referrals\ReferralProgram;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __invoke(Request $request, ReferralProgram $program): View
    {
        $customer = $request->user()->customerProfile();
        $cfg = $program->config();
        $code = $cfg['enabled'] ? $program->codeFor($customer) : null;

        return view('account.referrals', [
            'code' => $code,
            'cfg' => $cfg,
            'balance' => $program->balance($customer),
            'credits' => ReferralCredit::where('customer_id', $customer->id)->latest()->get(),
            'referrals' => $customer->referralsSent()->with('referred')->latest()->get(),
        ]);
    }
}
