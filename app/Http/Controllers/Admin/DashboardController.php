<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\CommissionRun;
use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $latestCommissionRun = CommissionRun::query()
            ->latest('year')
            ->latest('month')
            ->first();

        // Base summary on latest commission run period, not current calendar month.
        // This ensures "Total Sales" and "Total Commission" always refer to the same period.
        $dashMonth = $latestCommissionRun?->month ?? now()->month;
        $dashYear  = $latestCommissionRun?->year ?? now()->year;

        $selectedTopMonth = (int) $request->query('top_month', $dashMonth);
        $selectedTopYear = (int) $request->query('top_year', $dashYear);

        if ($selectedTopMonth < 1 || $selectedTopMonth > 12) {
            $selectedTopMonth = $dashMonth;
        }

        if ($selectedTopYear < 2020 || $selectedTopYear > 2100) {
            $selectedTopYear = $dashYear;
        }

        $mappedAffiliateSales = $latestCommissionRun
            ? DB::table('commission_entries')
                ->where('commission_run_id', $latestCommissionRun->id)
                ->where('commission_type', 'personal')
                ->sum('base_amount')
            : 0;

        $periodOrdersQuery = $this->ordersForPeriodQuery($dashMonth, $dashYear);
        $allImportedSales = (clone $periodOrdersQuery)->sum('estimated_commission_base');
        $noUplineOrdersQuery = (clone $periodOrdersQuery)
            ->where(function ($query): void {
                $query->whereNull('affiliate_id')
                    ->orWhere('sales_source', 'no_upline');
            });
        $noUplineSales = (clone $noUplineOrdersQuery)->sum('estimated_commission_base');
        $noUplineOrderCount = (clone $noUplineOrdersQuery)->count();
        $noUplineUsernameCount = (clone $noUplineOrdersQuery)
            ->distinct()
            ->count('creator_username_normalized');

        $currentCommissionRun = $latestCommissionRun;

        $selectedTopRun = CommissionRun::query()
            ->where('month', $selectedTopMonth)
            ->where('year', $selectedTopYear)
            ->first();
        $salesByAffiliate = collect();
        $commissionByAffiliate = collect();

        if ($selectedTopRun) {
            $salesByAffiliate = $selectedTopRun->commissionEntries()
                ->select('source_affiliate_id', DB::raw('SUM(base_amount) as total_sales'))
                ->where('commission_type', 'personal')
                ->groupBy('source_affiliate_id')
                ->pluck('total_sales', 'source_affiliate_id');

            $commissionByAffiliate = $selectedTopRun->commissionEntries()
                ->select('receiver_affiliate_id', DB::raw('SUM(commission_amount) as total_commission'))
                ->groupBy('receiver_affiliate_id')
                ->pluck('total_commission', 'receiver_affiliate_id');
        }

        $topAffiliateIds = $salesByAffiliate
            ->keys()
            ->unique()
            ->values();

        $affiliates = Affiliate::query()
            ->whereIn('id', $topAffiliateIds)
            ->get()
            ->keyBy('id');

        $topAffiliates = $topAffiliateIds
            ->map(function ($affiliateId) use ($affiliates, $salesByAffiliate, $commissionByAffiliate): ?array {
                $affiliate = $affiliates->get($affiliateId);

                if (! $affiliate) {
                    return null;
                }

                return [
                    'affiliate' => $affiliate,
                    'total_sales' => (float) ($salesByAffiliate->get($affiliateId) ?? 0),
                    'total_commission' => (float) ($commissionByAffiliate->get($affiliateId) ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row) => $row['total_sales'])
            ->take(10)
            ->values();

        $topAffiliateYears = CommissionRun::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->push($dashYear)
            ->push($selectedTopYear)
            ->unique()
            ->sortDesc()
            ->values();

        $months = $this->months();
        $periodLabel = $latestCommissionRun
            ? ($months[$latestCommissionRun->month].' '.$latestCommissionRun->year)
            : 'No Runs Yet';

        return view('admin.dashboard', [
            'summary' => [
                'total_sales_this_month' => (float) $mappedAffiliateSales,
                'all_imported_sales' => (float) $allImportedSales,
                'mapped_affiliate_sales' => (float) $mappedAffiliateSales,
                'no_upline_sales' => (float) $noUplineSales,
                'no_upline_order_count' => (int) $noUplineOrderCount,
                'no_upline_username_count' => (int) $noUplineUsernameCount,
                'total_commission_this_month' => (float) ($currentCommissionRun?->total_commission ?? 0),
                'period_label' => $periodLabel,
                'total_affiliates' => Affiliate::count(),
                'total_tiktok_accounts' => TiktokAccount::count(),
                'total_orders_imported' => TiktokOrder::count(),
                'active_rate_label' => 'Fixed',
                'total_commission_runs' => CommissionRun::count(),
            ],
            'recentCommissionRuns' => CommissionRun::query()
                ->latest()
                ->take(5)
                ->get()
                ->map(function (CommissionRun $run): CommissionRun {
                    $run->setAttribute('display_total_sales', (float) $this->ordersForPeriodQuery($run->month, $run->year)->sum('estimated_commission_base'));

                    return $run;
                }),
            'topAffiliates' => $topAffiliates,
            'selectedTopMonth' => $selectedTopMonth,
            'selectedTopYear' => $selectedTopYear,
            'selectedTopRun' => $selectedTopRun,
            'topAffiliateYears' => $topAffiliateYears,
            'months' => $months,
        ]);
    }

    private function months(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    private function ordersForPeriodQuery(int $month, int $year)
    {
        $start = now()->setDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return DB::table('tiktok_orders')
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->where(function ($query) use ($start, $end): void {
                $query
                    ->whereBetween('time_commission_paid', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end): void {
                        $query
                            ->whereNull('time_commission_paid')
                            ->whereBetween('time_created', [$start, $end]);
                    });
            });
    }
}
