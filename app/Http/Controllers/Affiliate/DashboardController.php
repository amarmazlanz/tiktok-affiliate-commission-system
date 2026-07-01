<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateTeamService;
use App\Services\AffiliateTierService;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        AffiliateCommissionService $commissions,
        AffiliateTeamService $teams,
        AffiliateTierService $tiers,
        BadgeService $badges,
    ): View|JsonResponse {
        $affiliate = $request->user()->affiliate;

        if (! $affiliate) {
            return view('affiliate.dashboard', ['affiliate' => null]);
        }

        $periodData = $commissions->periodData($affiliate, $request);
        $summaryData = $commissions->summary($affiliate, $periodData['periodFilters']);

        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json([
                'html' => view('affiliate.partials.commission-summary', array_merge(
                    $periodData,
                    $summaryData,
                    ['periodRoute' => route('affiliate.dashboard'), 'showManagerBonus' => false],
                ))->render(),
                'periodLabel' => $periodData['periodLabel'],
                'month' => $periodData['periodFilters']['month'],
                'year' => $periodData['periodFilters']['year'],
            ]);
        }

        $affiliate->loadMissing('upline:id,name,affiliate_code');
        $teamSummary = $teams->summary($affiliate);
        $loginEmail = $request->user()->email && ! str_ends_with($request->user()->email, '@inhouse.local')
            ? $request->user()->email
            : null;
        $tierData    = $tiers->forMonth($affiliate, (int) now()->month, (int) now()->year);
        $earnedBadges = $badges->earned($affiliate);

        return view('affiliate.dashboard', array_merge($periodData, $summaryData, [
            'affiliate' => $affiliate,
            'teamSummary' => $teamSummary,
            'tierData' => $tierData,
            'earnedBadges' => $earnedBadges,
            'profileSummary' => [
                'position' => $teamSummary['direct_count'] > 0 ? 'Manager' : 'Affiliate',
                'login_label' => $loginEmail ?: ($request->user()->affiliate_code ?: $affiliate->affiliate_code),
            ],
            'periodRoute' => route('affiliate.dashboard'),
            'showManagerBonus' => false,
        ]));
    }
}
