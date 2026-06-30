<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use App\Models\CommissionRun;
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

    public function show(Request $request, CommissionRun $commission): View|JsonResponse
    {
        $entryTypeLabels = $this->entryTypeLabels();
        $entryData = $this->commissionEntryData($request, $commission, $entryTypeLabels);

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

        $summaryQuery = $this->summaryQuery($commission->id)
            ->when($summaryGroup !== '', fn ($query) => $query->where('affiliates.group_name', $summaryGroup))
            ->when($summaryAffiliate, fn ($query) => $query->where('affiliates.id', $summaryAffiliate));
        $filteredSummarySalesTotal = (float) DB::query()
            ->fromSub(clone $summaryQuery, 'filtered_summaries')
            ->sum('total_sales');
        $totalOverriding = (float) DB::query()
            ->fromSub($this->summaryQuery($commission->id), 'commission_summaries')
            ->sum('total_overriding');

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

        return view('admin.commissions.show', [
            'commission' => $commission,
            'summaries' => $summaries,
            'filteredSummarySalesTotal' => $filteredSummarySalesTotal,
            'totalOverriding' => $totalOverriding,
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
            ],
            'months' => $this->months(),
            'entryTypeLabels' => $entryTypeLabels,
        ]);
    }

    private function commissionEntryData(Request $request, CommissionRun $commission, array $entryTypeLabels): array
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

    private function summaryQuery(int $commissionRunId)
    {
        $l1Condition = "commission_entries.commission_type IN ('l1_overriding', 'l1_split_seller', 'l1_split_upline') OR (commission_entries.commission_type = 'overriding' AND commission_entries.level = 1)";
        $l2Condition = "commission_entries.commission_type = 'l2_overriding' OR (commission_entries.commission_type = 'overriding' AND commission_entries.level = 2)";
        $l3Condition = "commission_entries.commission_type = 'l3_overriding' OR (commission_entries.commission_type = 'overriding' AND commission_entries.level = 3)";
        $overridingCondition = "commission_entries.commission_type = 'manager_bonus' OR {$l1Condition} OR {$l2Condition} OR {$l3Condition}";

        $salesSubquery = DB::table('commission_entries')
            ->select('source_affiliate_id', DB::raw('SUM(base_amount) as total_sales'))
            ->where('commission_run_id', $commissionRunId)
            ->where('commission_type', 'personal')
            ->groupBy('source_affiliate_id');

        return DB::table('commission_entries')
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


