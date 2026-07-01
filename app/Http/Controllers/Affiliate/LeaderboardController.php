<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\CommissionRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $availableRuns = CommissionRun::orderByDesc('year')->orderByDesc('month')->get();
        $latestRun     = $availableRuns->first();

        $selectedMonth = (int) $request->query('month', $latestRun?->month ?? now()->month);
        $selectedYear  = (int) $request->query('year', $latestRun?->year ?? now()->year);

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $latestRun?->month ?? now()->month;
        }
        if ($selectedYear < 2020 || $selectedYear > 2100) {
            $selectedYear = $latestRun?->year ?? now()->year;
        }

        $selectedRun = CommissionRun::where('month', $selectedMonth)->where('year', $selectedYear)->first();

        $leaderboard       = [];
        $ownRank           = null;
        $ownEntry          = null;
        $totalParticipants = 0;
        $topSales          = 0.0;

        if ($selectedRun) {
            $allRanked = DB::table('commission_entries')
                ->where('commission_run_id', $selectedRun->id)
                ->where('commission_type', 'personal')
                ->select('source_affiliate_id', DB::raw('SUM(base_amount) as total_sales'))
                ->groupBy('source_affiliate_id')
                ->orderByDesc('total_sales')
                ->get();

            $totalParticipants = $allRanked->count();
            $topSales          = (float) ($allRanked->first()?->total_sales ?? 0);

            $affiliateIds    = $allRanked->pluck('source_affiliate_id')->all();
            $affiliateModels = Affiliate::whereIn('id', $affiliateIds)
                ->select(['id', 'name', 'affiliate_code', 'affiliate_type'])
                ->get()
                ->keyBy('id');

            $tierThresholds = collect(config('affiliate_tiers.tiers', []))->sortByDesc('min_sales')->values();
            $getTier        = function (float $sales) use ($tierThresholds): array {
                foreach ($tierThresholds as $tier) {
                    if ($sales >= ($tier['min_sales'] ?? 0)) {
                        return $tier;
                    }
                }

                return $tierThresholds->last()?->toArray() ?? [
                    'key' => 'bronze',
                    'label' => 'Bronze',
                    'min_sales' => 0,
                    'css' => 'tier-bronze',
                ];
            };

            $allMapped = $allRanked->map(function ($row, int $idx) use ($affiliateModels, $getTier): array {
                $aff = $affiliateModels->get($row->source_affiliate_id);

                return [
                    'rank'           => $idx + 1,
                    'affiliate_id'   => (int) $row->source_affiliate_id,
                    'name'           => $aff?->name ?? '—',
                    'affiliate_code' => $aff?->affiliate_code,
                    'affiliate_type' => $aff?->affiliate_type ?? 'inhouse',
                    'total_sales'    => (float) $row->total_sales,
                    'tier'           => $getTier((float) $row->total_sales),
                ];
            });

            $leaderboard = $allMapped->take(10)->values()->all();

            $ownIdx = $allMapped->search(fn (array $r): bool => $r['affiliate_id'] === $affiliate->id);
            if ($ownIdx !== false) {
                $ownRank  = $ownIdx + 1;
                $ownEntry = $allMapped[$ownIdx];
            }
        }

        $availableYears = $availableRuns->pluck('year')->unique()->values();
        if ($availableYears->isEmpty()) {
            $availableYears = collect([now()->year]);
        }

        return view('affiliate.leaderboard', [
            'leaderboard'       => $leaderboard,
            'ownRank'           => $ownRank,
            'ownEntry'          => $ownEntry,
            'totalParticipants' => $totalParticipants,
            'topSales'          => $topSales,
            'selectedRun'       => $selectedRun,
            'selectedMonth'     => $selectedMonth,
            'selectedYear'      => $selectedYear,
            'availableYears'    => $availableYears,
            'months'            => $this->months(),
        ]);
    }

    private function months(): array
    {
        return [
            1 => 'January', 2 => 'February', 3 => 'March',
            4 => 'April',   5 => 'May',       6 => 'June',
            7 => 'July',    8 => 'August',    9 => 'September',
            10 => 'October', 11 => 'November', 12 => 'December',
        ];
    }
}
