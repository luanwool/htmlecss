<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralCredit;
use App\Services\Referrals\ReferralProgram;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(Request $request, ReferralProgram $program): View
    {
        return view('admin.referrals.index', [
            'cfg' => $program->config(),
            'stats' => [
                'codes' => ReferralCode::count(),
                'invited' => Referral::where('status', ReferralStatus::Invited->value)->count(),
                'rewarded' => Referral::where('status', ReferralStatus::Rewarded->value)->count(),
                'credits_granted' => (int) ReferralCredit::sum('amount_cents'),
                'credits_used' => (int) ReferralCredit::sum('used_cents'),
                'active_balance' => (int) (ReferralCredit::active()->sum('amount_cents') - ReferralCredit::active()->sum('used_cents')),
            ],
            'top' => ReferralCode::with('customer')->where('signups_count', '>', 0)->orderByDesc('signups_count')->limit(10)->get(),
            'referrals' => Referral::with(['referrer', 'referred'])->latest()->paginate(20),
        ]);
    }
}
