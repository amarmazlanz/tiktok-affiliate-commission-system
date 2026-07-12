<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Services\AffiliateReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InviteController extends Controller
{
    public function __invoke(Request $request, AffiliateReferralService $referrals): View
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);
        $referral = $referrals->ensureFor($affiliate);
        $baseUrl = $affiliate->referralUrl();

        return view('affiliate.invite', [
            'affiliate' => $affiliate,
            'referral' => $referral,
            'referralEnabled' => $affiliate->status === 'active' && $referral->is_active,
            'inhouseInviteUrl' => $baseUrl.'?type=inhouse',
            'onlineInviteUrl' => $baseUrl.'?type=online',
        ]);
    }
}
