<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\CommissionEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $isAjax = $request->expectsJson() || $request->boolean('ajax');
        $request->query->remove('ajax');
        $affiliate = $request->user()->affiliate;

        if (! $affiliate) {
            if ($isAjax) {
                return response()->json(['message' => 'Affiliate profile is not available.'], 422);
            }

            return view('affiliate.dashboard', [
                'affiliate' => null,
                'personalSales' => 0,
                'commissionSummary' => $this->emptyCommissionSummary(),
                'tiktokAccounts' => collect(),
                'directDownlines' => collect(),
                'teamTree' => null,
                'teamSummary' => [
                    'direct_count' => 0,
                    'total_count' => 0,
                    'level_2_count' => 0,
                    'level_3_plus_count' => 0,
                ],
                'recentOrders' => collect(),
                'profileSummary' => [],
                'commissionEntries' => collect(),
                'commissionSourceOptions' => collect(),
                'periodFilters' => [
                    'month' => 'all',
                    'year' => 'all',
                ],
                'availableYears' => collect([now()->year]),
                'months' => $this->months(),
                'commissionFilters' => [],
                'entryTypeLabels' => $this->entryTypeLabels(),
            ]);
        }

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
        $periodFilters = $this->resolvePeriodFilters($request, $defaultPeriod);
        $selectedMonth = $periodFilters['month'];
        $selectedYear = $periodFilters['year'];

        $availableYears = $availablePeriods
            ->pluck('year')
            ->merge($this->availableOrderYears($affiliate->id))
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->values();

        if ($availableYears->isEmpty()) {
            $availableYears->push(now()->year);
        }

        if ($selectedYear !== 'all') {
            $availableYears->push($selectedYear);
        }

        $availableYears = $availableYears
            ->unique()
            ->sortDesc()
            ->values();

        $personalSalesQuery = $affiliate->tiktokOrders()
            ->where('order_status', 'Settled');
        $this->applyOrderPeriod($personalSalesQuery, $selectedMonth, $selectedYear);
        $personalSales = $personalSalesQuery->sum('estimated_commission_base');

        $commissionSummaryQuery = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('commission_entries.receiver_affiliate_id', $affiliate->id)
            ->selectRaw("SUM(CASE WHEN commission_type = 'personal' THEN commission_amount ELSE 0 END) as personal")
            ->selectRaw("SUM(CASE WHEN commission_type = 'manager_bonus' THEN commission_amount ELSE 0 END) as manager_bonus")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_overriding' OR (commission_type = 'overriding' AND level = 1) THEN commission_amount ELSE 0 END) as l1_overriding")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_split_seller' THEN commission_amount ELSE 0 END) as l1_split_seller")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_split_upline' THEN commission_amount ELSE 0 END) as l1_split_upline")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l2_overriding' OR (commission_type = 'overriding' AND level = 2) THEN commission_amount ELSE 0 END) as l2_overriding")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l3_overriding' OR (commission_type = 'overriding' AND level = 3) THEN commission_amount ELSE 0 END) as l3_overriding");
        $this->applyCommissionPeriod($commissionSummaryQuery, $selectedMonth, $selectedYear);
        $commissionSummary = (array) $commissionSummaryQuery->first();

        $commissionSummary = array_map(fn ($value) => (float) $value, array_merge($this->emptyCommissionSummary(), $commissionSummary));
        $commissionSummary['total'] = array_sum(collect($commissionSummary)->except('total')->all());
        $commissionType = trim((string) $request->query('commission_type', ''));
        $sourceAffiliate = $request->filled('source_affiliate') ? $request->integer('source_affiliate') : null;
        $entryTypeLabels = $this->entryTypeLabels();
        $validTypes = array_keys($entryTypeLabels);

        if ($commissionType !== '' && ! in_array($commissionType, $validTypes, true)) {
            $commissionType = '';
        }

        if ($sourceAffiliate && ! $this->sourceAffiliateBelongsToReceivedEntries(
            $affiliate->id,
            $sourceAffiliate,
            $selectedMonth,
            $selectedYear,
        )) {
            $sourceAffiliate = null;
        }

        $commissionEntries = CommissionEntry::query()
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
            ->whereHas('commissionRun', function ($query) use ($selectedMonth, $selectedYear): void {
                $this->applyCommissionRunPeriod($query, $selectedMonth, $selectedYear);
            })
            ->latest('id')
            ->paginate(25, ['*'], 'commission_page')
            ->withQueryString();
        $baseReceivedQuery = CommissionEntry::query()
            ->where('receiver_affiliate_id', $affiliate->id);
        $commissionSourceOptions = (clone $baseReceivedQuery)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.source_affiliate_id')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->select('affiliates.id', 'affiliates.name', 'affiliates.affiliate_code')
            ->distinct()
            ->orderBy('affiliates.name');
        $this->applyCommissionPeriod($commissionSourceOptions, $selectedMonth, $selectedYear);
        $commissionSourceOptions = $commissionSourceOptions->get();
        $periodLabel = $this->periodLabel($selectedMonth, $selectedYear);
        $commissionViewData = [
            'personalSales' => $personalSales,
            'commissionSummary' => $commissionSummary,
            'commissionEntries' => $commissionEntries,
            'commissionSourceOptions' => $commissionSourceOptions,
            'periodFilters' => $periodFilters,
            'periodLabel' => $periodLabel,
            'availableYears' => $availableYears,
            'months' => $this->months(),
            'commissionFilters' => [
                'commission_type' => $commissionType,
                'source_affiliate' => $sourceAffiliate,
            ],
            'entryTypeLabels' => $entryTypeLabels,
        ];

        if ($isAjax) {
            return response()->json([
                'html' => view('affiliate.partials.commission-summary', $commissionViewData)->render(),
                'breakdownHtml' => view('affiliate.partials.commission-breakdown', $commissionViewData)->render(),
                'periodLabel' => $periodLabel,
                'month' => $selectedMonth,
                'year' => $selectedYear,
                'sourceAffiliate' => $sourceAffiliate,
            ]);
        }

        $affiliate->loadMissing('upline:id,name,affiliate_code');
        $teamTree = $this->teamHierarchy($affiliate);
        $teamSummary = [
            'direct_count' => $teamTree['direct_count'],
            'total_count' => $teamTree['total_team_count'],
            'level_2_count' => $teamTree['level_2_count'],
            'level_3_plus_count' => $teamTree['level_3_plus_count'],
        ];
        $directDownlineCount = $teamSummary['direct_count'];
        $totalTeamSize = $teamSummary['total_count'];
        $tiktokAccountsCount = $affiliate->tiktokAccounts()->count();
        $loginEmail = $request->user()->email && ! str_ends_with($request->user()->email, '@inhouse.local')
            ? $request->user()->email
            : null;

        return view('affiliate.dashboard', array_merge($commissionViewData, [
            'affiliate' => $affiliate,
            'profileSummary' => [
                'position' => $directDownlineCount > 0 ? 'Manager' : 'Affiliate',
                'direct_downline_count' => $directDownlineCount,
                'total_team_size' => $totalTeamSize,
                'tiktok_accounts_count' => $tiktokAccountsCount,
                'login_label' => $loginEmail ?: ($request->user()->affiliate_code ?: $affiliate->affiliate_code),
                'login_label_type' => $loginEmail ? 'Login Email' : 'Affiliate Login ID',
            ],
            'tiktokAccounts' => $affiliate->tiktokAccounts()
                ->select(['id', 'affiliate_id', 'username', 'username_normalized', 'status'])
                ->latest()
                ->get(),
            'directDownlines' => collect(),
            'teamTree' => $teamTree,
            'teamSummary' => $teamSummary,
            'recentOrders' => $affiliate->tiktokOrders()
                ->select(['id', 'affiliate_id', 'order_id', 'creator_username', 'order_status', 'estimated_commission_base', 'time_created'])
                ->latest('time_created')
                ->latest()
                ->limit(50)
                ->get(),
        ]));
    }

    private function sourceAffiliateBelongsToReceivedEntries(
        int $receiverAffiliateId,
        int $sourceAffiliateId,
        int|string $month,
        int|string $year,
    ): bool {
        $query = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('receiver_affiliate_id', $receiverAffiliateId)
            ->where('source_affiliate_id', $sourceAffiliateId);
        $this->applyCommissionPeriod($query, $month, $year);

        return $query->exists();
    }

    private function resolvePeriodFilters(Request $request, array $defaultPeriod): array
    {
        $month = $request->query('month');
        $year = $request->query('year');

        $month = $month === null
            ? $defaultPeriod['month']
            : ($month === 'all' ? 'all' : filter_var($month, FILTER_VALIDATE_INT));
        $year = $year === null
            ? $defaultPeriod['year']
            : ($year === 'all' ? 'all' : filter_var($year, FILTER_VALIDATE_INT));

        if ($month !== 'all' && (! is_int($month) || $month < 1 || $month > 12)) {
            $month = $defaultPeriod['month'];
        }

        if ($year !== 'all' && (! is_int($year) || $year < 2000 || $year > 2100)) {
            $year = $defaultPeriod['year'];
        }

        if ($year === 'all') {
            $month = 'all';
        }

        return ['month' => $month, 'year' => $year];
    }

    private function availableOrderYears(int $affiliateId)
    {
        $driver = DB::connection()->getDriverName();
        $yearExpression = $driver === 'sqlite'
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

    private function periodLabel(int|string $month, int|string $year): string
    {
        if ($year === 'all') {
            return 'Lifetime Performance';
        }

        if ($month === 'all') {
            return 'Year '.$year;
        }

        return $this->months()[$month].' '.$year;
    }

    private function teamHierarchy(Affiliate $root): array
    {
        $branchRows = collect(DB::select(
            <<<'SQL'
                WITH RECURSIVE affiliate_team(id, depth) AS (
                    SELECT id, 0
                    FROM affiliates
                    WHERE id = ?

                    UNION ALL

                    SELECT affiliates.id, affiliate_team.depth + 1
                    FROM affiliates
                    INNER JOIN affiliate_team ON affiliates.upline_id = affiliate_team.id
                    WHERE affiliate_team.depth < 100
                )
                SELECT id, depth FROM affiliate_team
            SQL,
            [$root->id],
        ));
        $depthById = $branchRows
            ->unique('id')
            ->mapWithKeys(fn ($row): array => [(int) $row->id => (int) $row->depth])
            ->all();
        $members = Affiliate::query()
            ->select(['id', 'upline_id', 'affiliate_code', 'affiliate_type', 'name', 'status'])
            ->with('tiktokAccounts:id,affiliate_id,username,username_normalized,status')
            ->whereKey(array_keys($depthById))
            ->orderBy('name')
            ->get()
            ->keyBy('id');
        $root = $members->get($root->id, $root);

        $childrenByParent = $members
            ->forget($root->id)
            ->groupBy(fn (Affiliate $affiliate): int => (int) $affiliate->upline_id);
        $buildNode = function (Affiliate $affiliate, array $ancestorIds = []) use (&$buildNode, $childrenByParent, $depthById): array {
            if (in_array($affiliate->id, $ancestorIds, true)) {
                return $this->emptyTeamNode($affiliate, $depthById[$affiliate->id] ?? 0);
            }

            $children = ($childrenByParent->get($affiliate->id) ?? collect())
                ->map(fn (Affiliate $child): array => $buildNode($child, [...$ancestorIds, $affiliate->id]))
                ->values();

            return [
                'affiliate' => $affiliate,
                'children' => $children,
                'depth' => $depthById[$affiliate->id] ?? 0,
                'direct_count' => $children->count(),
                'total_team_count' => $children->count() + $children->sum('total_team_count'),
                'level_2_count' => $children->sum('direct_count'),
                'level_3_plus_count' => $children->sum(fn (array $child): int => $child['level_2_count'] + $child['level_3_plus_count']),
            ];
        };

        return $buildNode($root);
    }

    private function emptyTeamNode(Affiliate $affiliate, int $depth): array
    {
        return [
            'affiliate' => $affiliate,
            'children' => collect(),
            'depth' => $depth,
            'direct_count' => 0,
            'total_team_count' => 0,
            'level_2_count' => 0,
            'level_3_plus_count' => 0,
        ];
    }

    private function emptyCommissionSummary(): array
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
