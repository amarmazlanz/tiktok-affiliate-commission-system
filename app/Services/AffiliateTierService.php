<?php

namespace App\Services;

use App\Models\Affiliate;
use Illuminate\Support\Facades\DB;

class AffiliateTierService
{
    public function forMonth(Affiliate $affiliate, int $month, int $year): array
    {
        $sales = (float) DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('commission_entries.source_affiliate_id', $affiliate->id)
            ->where('commission_entries.commission_type', 'personal')
            ->where('commission_runs.month', $month)
            ->where('commission_runs.year', $year)
            ->sum('commission_entries.base_amount');

        $tiers = collect(config('affiliate_tiers.tiers'))
            ->sortByDesc('min_sales')
            ->values();

        $current = $tiers->first(fn (array $tier): bool => $sales >= $tier['min_sales']) ?? $tiers->last();
        $currentIndex = $tiers->search(fn (array $tier): bool => $tier['key'] === $current['key']);
        $next = $currentIndex > 0 ? $tiers[$currentIndex - 1] : null;

        $progressPercent = 100;

        if ($next) {
            $range = max(1, $next['min_sales'] - $current['min_sales']);
            $progressPercent = (int) min(100, max(0, round((($sales - $current['min_sales']) / $range) * 100)));
        }

        return [
            'sales' => $sales,
            'current' => $current,
            'next' => $next,
            'amount_to_next' => $next ? max(0, $next['min_sales'] - $sales) : 0,
            'progress_percent' => $progressPercent,
        ];
    }
}
