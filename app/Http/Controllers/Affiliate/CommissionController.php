<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Services\AffiliateCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function __invoke(Request $request, AffiliateCommissionService $commissions): View|JsonResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $isAjax = $request->expectsJson() || $request->boolean('ajax');
        $request->query->remove('ajax');
        $periodData = $commissions->periodData($affiliate, $request);
        $summaryData = $commissions->summary($affiliate, $periodData['periodFilters']);
        $breakdownData = $commissions->breakdown($affiliate, $request, $periodData['periodFilters']);
        $data = array_merge($periodData, $summaryData, $breakdownData, [
            'affiliate' => $affiliate,
            'periodRoute' => route('affiliate.commission'),
            'showManagerBonus' => true,
        ]);

        if ($isAjax) {
            return response()->json([
                'html' => view('affiliate.partials.commission-summary', $data)->render(),
                'breakdownHtml' => view('affiliate.partials.commission-breakdown', $data)->render(),
                'periodLabel' => $periodData['periodLabel'],
                'month' => $periodData['periodFilters']['month'],
                'year' => $periodData['periodFilters']['year'],
                'sourceAffiliate' => $breakdownData['commissionFilters']['source_affiliate'],
            ]);
        }

        return view('affiliate.commission', $data);
    }
}
