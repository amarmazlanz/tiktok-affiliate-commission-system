<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\CommissionEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AffiliateCommissionService
{
    public function periodData(Affiliate $affiliate, Request $request): array
    {
        $availablePeriods = DB::table('commission_runs')
            ->join('commission_entries', 'commission_entries.commission_run_id', '=', 'commission_runs.id')
            ->where('commission_entries.receiver_affiliate_id', $affiliate->id)
            ->select('commission_runs.month', 'commission_runs.year')
            ->distinct()
            ->orderByDesc('commission_runs.year')
            ->orderByDesc('commission_runs.month')
            ->get();
        $latestPeriod = $availablePeriods->first();
        $latestOrderDate = $affiliate->tiktokOrders()
            ->where('order_status', 'Settled')
            ->whereNotNull('time_created')
            ->latest('time_created')
            ->value('time_created');
        $defaultPeriod = $latestPeriod
            ? ['month' => (int) $latestPeriod->month, 'year' => (int) $latestPeriod->year]
            : [
                'month' => $latestOrderDate ? Carbon::parse($latestOrderDate)->month : now()->month,
                'year' => $latestOrderDate ? Carbon::parse($latestOrderDate)->year : now()->year,
            ];
        $filters = $this->resolvePeriodFilters($request, $defaultPeriod);
        $years = $availablePeriods
            ->pluck('year')
            ->merge($this->availableOrderYears($affiliate->id))
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->values();

        if ($years->isEmpty()) {
            $years->push(now()->year);
        }

        if ($filters['year'] !== 'all') {
            $years->push($filters['year']);
        }

        return [
            'periodFilters' => $filters,
            'periodLabel' => $this->periodLabel($filters),
            'availableYears' => $years->unique()->sortDesc()->values(),
            'months' => $this->months(),
        ];
    }

    public function summary(Affiliate $affiliate, array $periodFilters): array
    {
        $month = $periodFilters['month'];
        $year = $periodFilters['year'];
        $dateFrom = $periodFilters['date_from'] ?? null;
        $dateTo = $periodFilters['date_to'] ?? null;

        $summaryQuery = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('commission_entries.receiver_affiliate_id', $affiliate->id)
            ->selectRaw("SUM(CASE WHEN commission_type = 'personal' THEN commission_amount ELSE 0 END) as personal")
            ->selectRaw("SUM(CASE WHEN commission_type = 'manager_bonus' THEN commission_amount ELSE 0 END) as manager_bonus")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_overriding' OR (commission_type = 'overriding' AND level = 1) THEN commission_amount ELSE 0 END) as l1_overriding")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_split_seller' THEN commission_amount ELSE 0 END) as l1_split_seller")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_split_upline' THEN commission_amount ELSE 0 END) as l1_split_upline")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l2_overriding' OR (commission_type = 'overriding' AND level = 2) THEN commission_amount ELSE 0 END) as l2_overriding")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l3_overriding' OR (commission_type = 'overriding' AND level = 3) THEN commission_amount ELSE 0 END) as l3_overriding");

        if ($dateFrom && $dateTo) {
            $summaryQuery->join('tiktok_orders as to_s', 'to_s.id', '=', 'commission_entries.tiktok_order_id')
                         ->whereBetween('to_s.time_commission_paid', [$dateFrom, $dateTo]);
        } else {
            $this->applyCommissionPeriod($summaryQuery, $month, $year);
        }

        $summary = array_map(
            fn ($value) => (float) $value,
            array_merge($this->emptySummary(), (array) $summaryQuery->first()),
        );
        $summary['total'] = array_sum(collect($summary)->except('total')->all());
        $summary['total_overriding'] = $summary['manager_bonus']
            + $summary['l1_overriding']
            + $summary['l1_split_seller']
            + $summary['l1_split_upline']
            + $summary['l2_overriding']
            + $summary['l3_overriding'];

        $personalSalesQuery = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('commission_entries.source_affiliate_id', $affiliate->id)
            ->where('commission_entries.commission_type', 'personal');

        if ($dateFrom && $dateTo) {
            $personalSalesQuery->join('tiktok_orders as to_ps', 'to_ps.id', '=', 'commission_entries.tiktok_order_id')
                               ->whereBetween('to_ps.time_commission_paid', [$dateFrom, $dateTo]);
        } else {
            $this->applyCommissionPeriod($personalSalesQuery, $month, $year);
        }

        return [
            'personalSales' => (float) $personalSalesQuery->sum('commission_entries.base_amount'),
            'commissionSummary' => $summary,
        ];
    }

    public function breakdown(Affiliate $affiliate, Request $request, array $periodFilters): array
    {
        $month = $periodFilters['month'];
        $year = $periodFilters['year'];
        $dateFrom = $periodFilters['date_from'] ?? null;
        $dateTo = $periodFilters['date_to'] ?? null;
        $typeLabels = $this->entryTypeLabels();
        $commissionType = trim((string) $request->query('commission_type', ''));
        $sourceAffiliate = $request->filled('source_affiliate') ? $request->integer('source_affiliate') : null;

        if ($commissionType !== '' && ! array_key_exists($commissionType, $typeLabels)) {
            $commissionType = '';
        }

        if ($sourceAffiliate && ! $this->validSource($affiliate->id, $sourceAffiliate, $periodFilters)) {
            $sourceAffiliate = null;
        }

        $entries = CommissionEntry::query()
            ->select([
                'id',
                'commission_run_id',
                'receiver_affiliate_id',
                'source_affiliate_id',
                'tiktok_order_id',
                'commission_type',
                'level',
                'rate',
                'base_amount',
                'commission_amount',
                'created_at',
            ])
            ->where('receiver_affiliate_id', $affiliate->id)
            ->with([
                'sourceAffiliate:id,name,affiliate_code',
                'tiktokOrder:id,order_id',
                'commissionRun:id,month,year',
            ])
            ->when($commissionType !== '', fn ($query) => $query->where('commission_type', $commissionType))
            ->when($sourceAffiliate, fn ($query) => $query->where('source_affiliate_id', $sourceAffiliate))
            ->when(
                $dateFrom && $dateTo,
                fn ($query) => $query->whereHas('tiktokOrder', fn ($q) => $q->whereBetween('time_commission_paid', [$dateFrom, $dateTo])),
                fn ($query) => $query->whereHas('commissionRun', function ($query) use ($month, $year): void {
                    $this->applyCommissionRunPeriod($query, $month, $year);
                })
            )
            ->latest('id')
            ->paginate(25, ['*'], 'commission_page')
            ->withQueryString();

        $sourceOptions = CommissionEntry::query()
            ->where('receiver_affiliate_id', $affiliate->id)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.source_affiliate_id')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->select('affiliates.id', 'affiliates.name', 'affiliates.affiliate_code')
            ->distinct()
            ->orderBy('affiliates.name');
        if ($dateFrom && $dateTo) {
            $sourceOptions->join('tiktok_orders as to_source_options', 'to_source_options.id', '=', 'commission_entries.tiktok_order_id')
                ->whereBetween('to_source_options.time_commission_paid', [$dateFrom, $dateTo]);
        } else {
            $this->applyCommissionPeriod($sourceOptions, $month, $year);
        }

        return [
            'commissionEntries' => $entries,
            'commissionSourceOptions' => $sourceOptions->get(),
            'commissionFilters' => [
                'commission_type' => $commissionType,
                'source_affiliate' => $sourceAffiliate,
            ],
            'entryTypeLabels' => $typeLabels,
        ];
    }

    private function validSource(int $receiverId, int $sourceId, array $periodFilters): bool
    {
        $month = $periodFilters['month'];
        $year = $periodFilters['year'];
        $dateFrom = $periodFilters['date_from'] ?? null;
        $dateTo = $periodFilters['date_to'] ?? null;

        $query = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('receiver_affiliate_id', $receiverId)
            ->where('source_affiliate_id', $sourceId);
        if ($dateFrom && $dateTo) {
            $query->join('tiktok_orders as to_valid_source', 'to_valid_source.id', '=', 'commission_entries.tiktok_order_id')
                ->whereBetween('to_valid_source.time_commission_paid', [$dateFrom, $dateTo]);
        } else {
            $this->applyCommissionPeriod($query, $month, $year);
        }

        return $query->exists();
    }

    private function resolvePeriodFilters(Request $request, array $defaultPeriod): array
    {
        $periodType = (string) $request->query('period_type', 'month');
        $allowedPeriodTypes = ['month', 'today', 'yesterday', 'last_7_days', 'this_month', 'last_month', 'custom'];

        if (! in_array($periodType, $allowedPeriodTypes, true)) {
            $periodType = 'month';
        }

        if (! $request->filled('period_type') && $request->filled('date_from') && $request->filled('date_to')) {
            $periodType = 'custom';
        }

        $month = $request->query('month');
        $year = $request->query('year');
        $month = $month === null ? $defaultPeriod['month'] : ($month === 'all' ? 'all' : filter_var($month, FILTER_VALIDATE_INT));
        $year = $year === null ? $defaultPeriod['year'] : ($year === 'all' ? 'all' : filter_var($year, FILTER_VALIDATE_INT));

        if ($month !== 'all' && (! is_int($month) || $month < 1 || $month > 12)) {
            $month = $defaultPeriod['month'];
        }

        if ($year !== 'all' && (! is_int($year) || $year < 2000 || $year > 2100)) {
            $year = $defaultPeriod['year'];
        }

        if ($year === 'all') {
            $month = 'all';
        }

        $dateFrom = null;
        $dateTo = null;
        $today = now();

        if ($periodType === 'today') {
            $dateFrom = $today->copy()->startOfDay();
            $dateTo = $today->copy()->endOfDay();
        } elseif ($periodType === 'yesterday') {
            $dateFrom = $today->copy()->subDay()->startOfDay();
            $dateTo = $today->copy()->subDay()->endOfDay();
        } elseif ($periodType === 'last_7_days') {
            $dateFrom = $today->copy()->subDays(6)->startOfDay();
            $dateTo = $today->copy()->endOfDay();
        } elseif ($periodType === 'this_month') {
            $dateFrom = $today->copy()->startOfMonth();
            $dateTo = $today->copy()->endOfMonth();
        } elseif ($periodType === 'last_month') {
            $dateFrom = $today->copy()->subMonthNoOverflow()->startOfMonth();
            $dateTo = $today->copy()->subMonthNoOverflow()->endOfMonth();
        }

        if ($periodType === 'custom' && $request->filled('date_from') && $request->filled('date_to')) {
            try {
                $df = Carbon::createFromFormat('Y-m-d', $request->input('date_from'))->startOfDay();
                $dt = Carbon::createFromFormat('Y-m-d', $request->input('date_to'))->endOfDay();

                if ($df->lte($dt)) {
                    $dateFrom = $df;
                    $dateTo = $dt;
                }
            } catch (\Throwable) {
                // invalid dates - ignore
            }
        }

        if ($periodType !== 'month' && (! $dateFrom || ! $dateTo)) {
            $periodType = 'month';
        }

        return [
            'period_type' => $periodType,
            'month' => $month,
            'year' => $year,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }
    private function availableOrderYears(int $affiliateId): Collection
    {
        $yearExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', time_created) AS INTEGER)"
            : 'YEAR(time_created)';

        return DB::table('tiktok_orders')
            ->where('affiliate_id', $affiliateId)
            ->whereNotNull('time_created')
            ->selectRaw($yearExpression.' as year')
            ->distinct()
            ->pluck('year');
    }

    private function applyOrderPeriod($query, int|string $month, int|string $year): void
    {
        if ($year !== 'all') {
            $query->whereYear('time_created', $year);
        }
        if ($month !== 'all') {
            $query->whereMonth('time_created', $month);
        }
    }

    private function applyCommissionPeriod($query, int|string $month, int|string $year): void
    {
        if ($year !== 'all') {
            $query->where('commission_runs.year', $year);
        }
        if ($month !== 'all') {
            $query->where('commission_runs.month', $month);
        }
    }

    private function applyCommissionRunPeriod($query, int|string $month, int|string $year): void
    {
        if ($year !== 'all') {
            $query->where('year', $year);
        }
        if ($month !== 'all') {
            $query->where('month', $month);
        }
    }

    private function periodLabel(array $filters): string
    {
        $month = $filters['month'];
        $year = $filters['year'];
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $periodType = $filters['period_type'] ?? 'month';

        if ($periodType !== 'month' && $dateFrom && $dateTo) {
            return match ($periodType) {
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last_7_days' => 'Last 7 Days',
                'this_month' => 'This Month',
                'last_month' => 'Last Month',
                'custom' => $dateFrom->format('d M Y').' - '.$dateTo->format('d M Y'),
                default => $dateFrom->format('d M Y').' - '.$dateTo->format('d M Y'),
            };
        }

        if ($year === 'all') {
            return 'Lifetime Performance';
        }

        if ($month === 'all') {
            return 'Year '.$year;
        }

        return $this->months()[$month].' '.$year;
    }
    private function emptySummary(): array
    {
        return [
            'personal' => 0,
            'manager_bonus' => 0,
            'l1_overriding' => 0,
            'l1_split_seller' => 0,
            'l1_split_upline' => 0,
            'l2_overriding' => 0,
            'l3_overriding' => 0,
            'total' => 0,
        ];
    }

    private function entryTypeLabels(): array
    {
        return [
            'personal' => 'Personal Commission',
            'manager_bonus' => 'Manager Bonus',
            'overriding' => 'Overriding',
            'l1_overriding' => 'L1 Overriding',
            'l1_split_seller' => 'L1 Split - Seller 70%',
            'l1_split_upline' => 'L1 Split - Upline 30%',
            'l2_overriding' => 'L2 Overriding',
            'l3_overriding' => 'L3 Overriding',
        ];
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
