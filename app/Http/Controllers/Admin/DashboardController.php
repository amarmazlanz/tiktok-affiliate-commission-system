<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\CommissionRateSetting;
use App\Models\CommissionRun;
use App\Models\TiktokAccount;
use App\Models\TiktokOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $month = now()->month;
        $year = now()->year;

        $salesDateScope = function ($query) use ($month, $year): void {
            $query
                ->where(function ($query) use ($month, $year): void {
                    $query
                        ->whereMonth('time_commission_paid', $month)
                        ->whereYear('time_commission_paid', $year);
                })
                ->orWhere(function ($query) use ($month, $year): void {
                    $query
                        ->whereNull('time_commission_paid')
                        ->whereMonth('time_created', $month)
                        ->whereYear('time_created', $year);
                });
        };

        $totalSalesThisMonth = TiktokOrder::query()
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->where($salesDateScope)
            ->sum('estimated_commission_base');

        $currentCommissionRun = CommissionRun::query()
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $activeRateSetting = CommissionRateSetting::query()
            ->where('status', 'active')
            ->where('month', $month)
            ->where('year', $year)
            ->first()
            ?? CommissionRateSetting::query()
                ->where('status', 'active')
                ->latest('year')
                ->latest('month')
                ->first();

        $salesByAffiliate = TiktokOrder::query()
            ->select('affiliate_id', DB::raw('SUM(estimated_commission_base) as total_sales'))
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->where($salesDateScope)
            ->groupBy('affiliate_id')
            ->pluck('total_sales', 'affiliate_id');

        $commissionByAffiliate = collect();

        if ($currentCommissionRun) {
            $commissionByAffiliate = $currentCommissionRun->commissionEntries()
                ->select('receiver_affiliate_id', DB::raw('SUM(commission_amount) as total_commission'))
                ->groupBy('receiver_affiliate_id')
                ->pluck('total_commission', 'receiver_affiliate_id');
        }

        $topAffiliateIds = $salesByAffiliate
            ->keys()
            ->merge($commissionByAffiliate->keys())
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
            ->sortByDesc(fn (array $row) => $row['total_sales'] ?: $row['total_commission'])
            ->take(5)
            ->values();

        return view('admin.dashboard', [
            'summary' => [
                'total_sales_this_month' => (float) $totalSalesThisMonth,
                'total_commission_this_month' => (float) ($currentCommissionRun?->total_commission ?? 0),
                'total_affiliates' => Affiliate::count(),
                'total_tiktok_accounts' => TiktokAccount::count(),
                'total_orders_imported' => TiktokOrder::count(),
                'active_rate_label' => $activeRateSetting
                    ? sprintf('%02d/%d', $activeRateSetting->month, $activeRateSetting->year)
                    : '-',
                'total_commission_runs' => CommissionRun::count(),
            ],
            'recentCommissionRuns' => CommissionRun::query()
                ->latest()
                ->take(5)
                ->get(),
            'topAffiliates' => $topAffiliates,
            'months' => $this->months(),
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
}
