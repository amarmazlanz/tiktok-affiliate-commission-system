<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use App\Models\CommissionRun;
use App\Models\TiktokOrder;
use App\Services\CommissionCalculatorService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(): View
    {
        try {
            $runs = CommissionRun::query()->latest('year')->latest('month')->paginate(15);
            $runs->getCollection()->transform(function (CommissionRun $run): CommissionRun {
                $run->setAttribute('display_total_sales', $this->totalImportedSalesForPeriod($run->month, $run->year));

                return $run;
            });
            $setupError = null;
        } catch (\Throwable $exception) {
            Log::error('Commission runs page failed to load.', [
                'user_id' => request()->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            $runs = new LengthAwarePaginator([], 0, 15, 1, [
                'path' => request()->url(),
            ]);
            $setupError = 'Commission Runs tidak dapat dibaca. Sila pastikan migration production sudah dijalankan dan cache sudah dibersihkan.';
        }

        return view('admin.commissions.index', [
            'runs' => $runs,
            'months' => $this->months(),
            'setupError' => $setupError,
        ]);
    }

    public function store(Request $request, CommissionCalculatorService $calculator): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'report_status' => ['required', Rule::in(['provisional', 'final'])],
            'confirm_final_recalculate' => ['nullable', 'boolean'],
        ]);

        $existingRun = CommissionRun::query()
            ->where('month', (int) $data['month'])
            ->where('year', (int) $data['year'])
            ->first();

        if ($existingRun?->status === 'final' && ! $request->boolean('confirm_final_recalculate')) {
            return back()
                ->withInput()
                ->withErrors(['confirm_final_recalculate' => 'Report ini sudah Final. Sila confirm sebelum recalculate.']);
        }

        try {
            $run = $calculator->calculate((int) $data['month'], (int) $data['year'], $data['report_status']);
        } catch (\Throwable $exception) {
            Log::error('Commission calculation failed.', [
                'month' => (int) $data['month'],
                'year' => (int) $data['year'],
                'report_status' => $data['report_status'],
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['commission' => 'Commission calculation gagal dijalankan. Sila semak server log atau cuba semula selepas data/import disahkan.']);
        }

        return redirect()
            ->route('admin.commissions.show', $run)
            ->with('success', 'Commission calculation berjaya dijalankan.');
    }

    public function destroy(CommissionRun $commission): RedirectResponse
    {
        $label = $this->months()[$commission->month].' '.$commission->year;

        DB::transaction(function () use ($commission): void {
            CommissionEntry::where('commission_run_id', $commission->id)->delete();
            $commission->delete();
        });

        Log::info('Commission run deleted.', [
            'commission_run_id' => $commission->id,
            'month' => $commission->month,
            'year' => $commission->year,
            'status' => $commission->status,
            'user_id' => request()->user()?->id,
        ]);

        return redirect()
            ->route('admin.commissions.index')
            ->with('success', "Commission run {$label} ({$commission->status}) telah dipadam. Anda boleh run semula bila-bila masa.");
    }

    public function show(Request $request, CommissionRun $commission): View|JsonResponse
    {
        $dateFrom = null;
        $dateTo = null;

        if ($request->filled('date_from') && $request->filled('date_to')) {
            try {
                $df = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('date_from'))->startOfDay();
                $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('date_to'))->endOfDay();
                if ($df->lte($dt)) {
                    $dateFrom = $df;
                    $dateTo = $dt;
                }
            } catch (\Throwable) {
                // invalid dates — ignore
            }
        }

        $entryTypeLabels = $this->entryTypeLabels();
        $entryData = $this->commissionEntryData($request, $commission, $entryTypeLabels, $dateFrom, $dateTo);

        if ($request->boolean('ajax') || $request->expectsJson()) {
            return response()->json([
                'html' => view('admin.commissions.partials.entry-results', [
                    'commissionEntries' => $entryData['commissionEntries'],
                    'entryTypeLabels' => $entryTypeLabels,
                ])->render(),
                'receiverOptions' => $entryData['receiverOptions']->map(fn ($receiver): array => [
                    'id' => $receiver->id,
                    'name' => $receiver->name,
                    'affiliate_code' => $receiver->affiliate_code,
                    'label' => trim($receiver->name.' '.($receiver->affiliate_code ? '('.$receiver->affiliate_code.')' : '')),
                ])->values(),
                'receiverHtml' => view('admin.commissions.partials.receiver-combobox', [
                    'receiverOptions' => $entryData['receiverOptions'],
                    'selectedReceiver' => $entryData['filters']['receiver'],
                ])->render(),
                'sourceOptions' => $entryData['sourceOptions']->map(fn ($source): array => [
                    'id' => $source->id,
                    'name' => $source->name,
                    'affiliate_code' => $source->affiliate_code,
                    'label' => trim($source->name.' '.($source->affiliate_code ? '('.$source->affiliate_code.')' : '')),
                ])->values(),
                'sourceHtml' => view('admin.commissions.partials.source-combobox', [
                    'sourceOptions' => $entryData['sourceOptions'],
                    'selectedSource' => $entryData['filters']['source'],
                ])->render(),
                'selectedReceiver' => $entryData['filters']['receiver'],
                'selectedSource' => $entryData['filters']['source'],
                'resultCount' => $entryData['commissionEntries']->total(),
            ]);
        }

        $summaryPerPage = 50;
        $summarySort = in_array($request->query('summary_sort'), [
            'affiliate',
            'total_sales',
            'personal',
            'manager_bonus',
            'l1_earnings',
            'l2_earnings',
            'l3_earnings',
            'total_overriding',
            'total',
        ], true) ? (string) $request->query('summary_sort') : 'affiliate';
        $summaryDirection = $request->query('summary_dir') === 'desc' ? 'desc' : 'asc';
        $summaryGroup = trim((string) $request->query('summary_group', ''));
        $summaryAffiliate = $request->filled('summary_affiliate') ? $request->integer('summary_affiliate') : null;

        if ($summaryAffiliate && ! $this->affiliateBelongsToGroup($summaryAffiliate, $summaryGroup)) {
            $summaryAffiliate = null;
        }

        $summaryQuery = $this->summaryQuery($commission->id, $dateFrom, $dateTo)
            ->when($summaryGroup !== '', fn ($query) => $query->where('affiliates.group_name', $summaryGroup))
            ->when($summaryAffiliate, fn ($query) => $query->where('affiliates.id', $summaryAffiliate));
        $filteredSummarySalesTotal = (float) DB::query()
            ->fromSub(clone $summaryQuery, 'filtered_summaries')
            ->sum('total_sales');
        $totalOverriding = (float) DB::query()
            ->fromSub($this->summaryQuery($commission->id, $dateFrom, $dateTo), 'commission_summaries')
            ->sum('total_overriding');
        $mappedAffiliateSalesTotal = (float) DB::table('commission_entries')
            ->where('commission_run_id', $commission->id)
            ->where('commission_type', 'personal')
            ->when($dateFrom && $dateTo, fn ($q) =>
                $q->join('tiktok_orders as to_mapped', 'to_mapped.id', '=', 'commission_entries.tiktok_order_id')
                  ->whereBetween('to_mapped.time_commission_paid', [$dateFrom, $dateTo])
            )
            ->sum('base_amount');
        $totalImportedSales = $this->totalImportedSalesForPeriod($commission->month, $commission->year, $dateFrom, $dateTo);
        $noUplineSales = $this->noUplineSalesQuery($commission->month, $commission->year, $dateFrom, $dateTo);
        $noUplineTotals = [
            'total_sales' => (float) (clone $noUplineSales)->sum('estimated_commission_base'),
            'order_count' => (int) (clone $noUplineSales)->count(),
            'username_count' => (int) (clone $noUplineSales)->distinct()->count('creator_username_normalized'),
        ];
        $noUplineSalesRows = (clone $noUplineSales)
            ->select([
                'creator_username_normalized',
                DB::raw('MAX(creator_username) as creator_username'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(estimated_commission_base) as total_sales'),
                DB::raw('SUM(COALESCE(actual_commission_payment, 0)) as total_tiktok_commission_paid'),
                DB::raw('MIN(time_commission_paid) as first_sale_date'),
                DB::raw('MAX(time_commission_paid) as last_sale_date'),
            ])
            ->groupBy('creator_username_normalized')
            ->orderByDesc('total_sales')
            ->paginate(15, ['*'], 'no_upline_page')
            ->withQueryString();

        $summaryOrderColumn = $summarySort === 'affiliate' ? 'affiliate_name' : $summarySort;
        $summaries = $summaryQuery
            ->orderBy($summaryOrderColumn, $summaryDirection)
            ->paginate($summaryPerPage, ['*'], 'summary_page')
            ->withQueryString();

        $baseEntryQuery = CommissionEntry::query()->where('commission_run_id', $commission->id);
        $summaryAffiliateOptions = (clone $baseEntryQuery)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.receiver_affiliate_id')
            ->select('affiliates.id', 'affiliates.name', 'affiliates.affiliate_code')
            ->when($summaryGroup !== '', fn ($query) => $query->where('affiliates.group_name', $summaryGroup))
            ->distinct()
            ->orderBy('affiliates.name')
            ->get();
        $summaryGroupOptions = (clone $baseEntryQuery)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.receiver_affiliate_id')
            ->whereNotNull('affiliates.group_name')
            ->where('affiliates.group_name', '<>', '')
            ->distinct()
            ->orderBy('affiliates.group_name')
            ->pluck('affiliates.group_name');

        $filteredTotalCommission = $dateFrom && $dateTo
            ? (float) DB::table('commission_entries')
                ->where('commission_run_id', $commission->id)
                ->join('tiktok_orders as to_tc', 'to_tc.id', '=', 'commission_entries.tiktok_order_id')
                ->whereBetween('to_tc.time_commission_paid', [$dateFrom, $dateTo])
                ->sum('commission_amount')
            : (float) $commission->total_commission;

        return view('admin.commissions.show', [
            'commission' => $commission,
            'summaries' => $summaries,
            'filteredSummarySalesTotal' => $filteredSummarySalesTotal,
            'mappedAffiliateSalesTotal' => $mappedAffiliateSalesTotal,
            'totalImportedSales' => $totalImportedSales,
            'totalOverriding' => $totalOverriding,
            'filteredTotalCommission' => $filteredTotalCommission,
            'noUplineTotals' => $noUplineTotals,
            'noUplineSalesRows' => $noUplineSalesRows,
            'commissionEntries' => $entryData['commissionEntries'],
            'summaryAffiliateOptions' => $summaryAffiliateOptions,
            'receiverOptions' => $entryData['receiverOptions'],
            'sourceOptions' => $entryData['sourceOptions'],
            'typeOptions' => $entryData['typeOptions'],
            'summaryGroupOptions' => $summaryGroupOptions,
            'filters' => [
                ...$entryData['filters'],
                'summary_group' => $summaryGroup,
                'summary_affiliate' => $summaryAffiliate,
                'summary_sort' => $summarySort,
                'summary_dir' => $summaryDirection,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
            'months' => $this->months(),
            'entryTypeLabels' => $entryTypeLabels,
        ]);
    }

    private function commissionEntryData(Request $request, CommissionRun $commission, array $entryTypeLabels, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null): array
    {
        $perPage = in_array((int) $request->query('per_page', 50), [50, 100, 200], true)
            ? (int) $request->query('per_page', 50)
            : 50;
        $entryGroup = trim((string) $request->query('entry_group', ''));
        $receiver = $request->filled('receiver') ? $request->integer('receiver') : null;
        $source = $request->filled('source') ? $request->integer('source') : null;
        $type = (string) $request->query('type', '');
        $orderId = mb_substr(trim((string) $request->query('order_id', '')), 0, 255);

        if ($receiver && ! $this->affiliateBelongsToGroup($receiver, $entryGroup)) {
            $receiver = null;
        }

        if ($source && ! $this->affiliateBelongsToGroup($source, $entryGroup)) {
            $source = null;
        }

        if ($type !== '' && ! array_key_exists($type, $entryTypeLabels)) {
            $type = '';
        }

        $entryQuery = CommissionEntry::query()
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
            ])
            ->where('commission_run_id', $commission->id)
            ->with([
                'receiverAffiliate:id,name',
                'sourceAffiliate:id,name',
                'tiktokOrder:id,order_id',
            ])
            ->when($entryGroup !== '', fn ($query) => $query->whereHas('receiverAffiliate', fn ($query) => $query->where('group_name', $entryGroup)))
            ->when($receiver, fn ($query) => $query->where('receiver_affiliate_id', $receiver))
            ->when($source, fn ($query) => $query->where('source_affiliate_id', $source))
            ->when($type !== '', fn ($query) => $query->where('commission_type', $type))
            ->when($orderId !== '', fn ($query) => $query->whereHas('tiktokOrder', fn ($query) => $query->where('order_id', 'like', '%'.$orderId.'%')))
            ->when($dateFrom && $dateTo, fn ($query) => $query->whereHas('tiktokOrder', fn ($query) => $query->whereBetween('time_commission_paid', [$dateFrom, $dateTo])))
            ->latest('id');

        $commissionEntries = $entryQuery
            ->paginate($perPage, ['*'], 'entries_page')
            ->withQueryString();
        $baseEntryQuery = CommissionEntry::query()->where('commission_run_id', $commission->id);
        $receiverOptions = (clone $baseEntryQuery)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.receiver_affiliate_id')
            ->select('affiliates.id', 'affiliates.name', 'affiliates.affiliate_code')
            ->when($entryGroup !== '', fn ($query) => $query->where('affiliates.group_name', $entryGroup))
            ->distinct()
            ->orderBy('affiliates.name')
            ->get();
        $sourceOptions = (clone $baseEntryQuery)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.source_affiliate_id')
            ->select('affiliates.id', 'affiliates.name', 'affiliates.affiliate_code')
            ->when($entryGroup !== '', fn ($query) => $query->where('affiliates.group_name', $entryGroup))
            ->distinct()
            ->orderBy('affiliates.name')
            ->get();
        $typeOptions = (clone $baseEntryQuery)
            ->select('commission_type')
            ->distinct()
            ->orderBy('commission_type')
            ->pluck('commission_type')
            ->map(fn ($entryType) => [
                'value' => $entryType,
                'label' => $entryTypeLabels[$entryType] ?? ucfirst(str_replace('_', ' ', $entryType)),
            ]);

        return [
            'commissionEntries' => $commissionEntries,
            'receiverOptions' => $receiverOptions,
            'sourceOptions' => $sourceOptions,
            'typeOptions' => $typeOptions,
            'filters' => [
                'entry_group' => $entryGroup,
                'receiver' => $receiver,
                'source' => $source,
                'type' => $type,
                'order_id' => $orderId,
                'per_page' => $perPage,
            ],
        ];
    }

    private function summaryQuery(int $commissionRunId, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null)
    {
        $l1Condition = "commission_entries.commission_type IN ('l1_overriding', 'l1_split_seller', 'l1_split_upline') OR (commission_entries.commission_type = 'overriding' AND commission_entries.level = 1)";
        $l2Condition = "commission_entries.commission_type = 'l2_overriding' OR (commission_entries.commission_type = 'overriding' AND commission_entries.level = 2)";
        $l3Condition = "commission_entries.commission_type = 'l3_overriding' OR (commission_entries.commission_type = 'overriding' AND commission_entries.level = 3)";
        $overridingCondition = "commission_entries.commission_type = 'manager_bonus' OR {$l1Condition} OR {$l2Condition} OR {$l3Condition}";

        $salesSubquery = DB::table('commission_entries')
            ->select('source_affiliate_id', DB::raw('SUM(base_amount) as total_sales'))
            ->where('commission_run_id', $commissionRunId)
            ->where('commission_type', 'personal');

        if ($dateFrom && $dateTo) {
            $salesSubquery->join('tiktok_orders as to_sales', 'to_sales.id', '=', 'commission_entries.tiktok_order_id')
                          ->whereBetween('to_sales.time_commission_paid', [$dateFrom, $dateTo]);
        }

        $salesSubquery->groupBy('source_affiliate_id');

        $query = DB::table('commission_entries')
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.receiver_affiliate_id')
            ->leftJoinSub($salesSubquery, 'sales', fn ($join) => $join->on('sales.source_affiliate_id', '=', 'affiliates.id'))
            ->where('commission_entries.commission_run_id', $commissionRunId)
            ->groupBy('affiliates.id', 'affiliates.name', 'affiliates.group_name', 'affiliates.affiliate_type', 'sales.total_sales')
            ->select([
                'affiliates.id as affiliate_id',
                'affiliates.name as affiliate_name',
                'affiliates.group_name',
                'affiliates.affiliate_type',
                DB::raw('COALESCE(sales.total_sales, 0) as total_sales'),
                DB::raw("SUM(CASE WHEN commission_entries.commission_type = 'personal' THEN commission_entries.commission_amount ELSE 0 END) as personal"),
                DB::raw("SUM(CASE WHEN commission_entries.commission_type = 'manager_bonus' THEN commission_entries.commission_amount ELSE 0 END) as manager_bonus"),
                DB::raw("SUM(CASE WHEN {$l1Condition} THEN commission_entries.commission_amount ELSE 0 END) as l1_earnings"),
                DB::raw("SUM(CASE WHEN {$l2Condition} THEN commission_entries.commission_amount ELSE 0 END) as l2_earnings"),
                DB::raw("SUM(CASE WHEN {$l3Condition} THEN commission_entries.commission_amount ELSE 0 END) as l3_earnings"),
                DB::raw("SUM(CASE WHEN {$overridingCondition} THEN commission_entries.commission_amount ELSE 0 END) as total_overriding"),
                DB::raw("SUM(CASE WHEN commission_entries.commission_type = 'personal' OR {$overridingCondition} THEN commission_entries.commission_amount ELSE 0 END) as total"),
            ]);

        if ($dateFrom && $dateTo) {
            $query->join('tiktok_orders as to_filter', 'to_filter.id', '=', 'commission_entries.tiktok_order_id')
                  ->whereBetween('to_filter.time_commission_paid', [$dateFrom, $dateTo]);
        }

        return $query;
    }

    private function affiliateBelongsToGroup(int $affiliateId, string $groupName): bool
    {
        if ($groupName === '') {
            return true;
        }

        return DB::table('affiliates')
            ->where('id', $affiliateId)
            ->where('group_name', $groupName)
            ->exists();
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

    private function noUplineSalesQuery(int $month, int $year, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null)
    {
        $start = $dateFrom ?? now()->setDate($year, $month, 1)->startOfMonth();
        $end = $dateTo ?? now()->setDate($year, $month, 1)->endOfMonth();

        return TiktokOrder::query()
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('affiliate_id')
                    ->orWhere('sales_source', 'no_upline');
            })
            ->whereNotNull('time_commission_paid')
            ->whereBetween('time_commission_paid', [$start, $end]);
    }

    private function totalImportedSalesForPeriod(int $month, int $year, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null): float
    {
        $start = $dateFrom ?? now()->setDate($year, $month, 1)->startOfMonth();
        $end = $dateTo ?? now()->setDate($year, $month, 1)->endOfMonth();

        return (float) TiktokOrder::query()
            ->where('order_status', 'Settled')
            ->where('estimated_commission_base', '>', 0)
            ->whereNotNull('time_commission_paid')
            ->whereBetween('time_commission_paid', [$start, $end])
            ->sum('estimated_commission_base');
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
