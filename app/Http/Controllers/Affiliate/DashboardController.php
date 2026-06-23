<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\CommissionEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $affiliate = $request->user()->affiliate;

        if (! $affiliate) {
            return view('affiliate.dashboard', [
                'affiliate' => null,
                'personalSales' => 0,
                'commissionSummary' => $this->emptyCommissionSummary(),
                'tiktokAccounts' => collect(),
                'directDownlines' => collect(),
                'recentOrders' => collect(),
                'profileSummary' => [],
                'commissionEntries' => collect(),
                'commissionSourceOptions' => collect(),
                'commissionPeriodOptions' => collect(),
                'commissionFilters' => [],
                'entryTypeLabels' => $this->entryTypeLabels(),
            ]);
        }

        $affiliate->loadMissing('upline:id,name,affiliate_code');
        $personalSales = $affiliate->tiktokOrders()
            ->where('order_status', 'Settled')
            ->sum('estimated_commission_base');

        $commissionSummary = (array) DB::table('commission_entries')
            ->where('receiver_affiliate_id', $affiliate->id)
            ->selectRaw("SUM(CASE WHEN commission_type = 'personal' THEN commission_amount ELSE 0 END) as personal")
            ->selectRaw("SUM(CASE WHEN commission_type = 'manager_bonus' THEN commission_amount ELSE 0 END) as manager_bonus")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_overriding' OR (commission_type = 'overriding' AND level = 1) THEN commission_amount ELSE 0 END) as l1_overriding")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_split_seller' THEN commission_amount ELSE 0 END) as l1_split_seller")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l1_split_upline' THEN commission_amount ELSE 0 END) as l1_split_upline")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l2_overriding' OR (commission_type = 'overriding' AND level = 2) THEN commission_amount ELSE 0 END) as l2_overriding")
            ->selectRaw("SUM(CASE WHEN commission_type = 'l3_overriding' OR (commission_type = 'overriding' AND level = 3) THEN commission_amount ELSE 0 END) as l3_overriding")
            ->first();

        $commissionSummary = array_map(fn ($value) => (float) $value, array_merge($this->emptyCommissionSummary(), $commissionSummary));
        $commissionSummary['total'] = array_sum(collect($commissionSummary)->except('total')->all());
        $directDownlineCount = $affiliate->directDownlines()->count();
        $totalTeamSize = $this->totalTeamSize($affiliate->id);
        $tiktokAccountsCount = $affiliate->tiktokAccounts()->count();
        $loginEmail = $request->user()->email && ! str_ends_with($request->user()->email, '@inhouse.local')
            ? $request->user()->email
            : null;
        $commissionType = trim((string) $request->query('commission_type', ''));
        $sourceAffiliate = $request->filled('source_affiliate') ? $request->integer('source_affiliate') : null;
        $period = trim((string) $request->query('commission_period', ''));
        $entryTypeLabels = $this->entryTypeLabels();
        $validTypes = array_keys($entryTypeLabels);

        if ($commissionType !== '' && ! in_array($commissionType, $validTypes, true)) {
            $commissionType = '';
        }

        if ($sourceAffiliate && ! $this->sourceAffiliateBelongsToReceivedEntries($affiliate->id, $sourceAffiliate)) {
            $sourceAffiliate = null;
        }

        if ($period !== '' && ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = '';
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
            ->when($period !== '', function ($query) use ($period): void {
                [$year, $month] = array_map('intval', explode('-', $period));

                $query->whereHas('commissionRun', fn ($query) => $query
                    ->where('year', $year)
                    ->where('month', $month));
            })
            ->latest('id')
            ->paginate(25, ['*'], 'commission_page')
            ->withQueryString();
        $baseReceivedQuery = CommissionEntry::query()
            ->where('receiver_affiliate_id', $affiliate->id);
        $commissionSourceOptions = (clone $baseReceivedQuery)
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.source_affiliate_id')
            ->select('affiliates.id', 'affiliates.name', 'affiliates.affiliate_code')
            ->distinct()
            ->orderBy('affiliates.name')
            ->get();
        $commissionPeriodOptions = (clone $baseReceivedQuery)
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->select('commission_runs.month', 'commission_runs.year')
            ->distinct()
            ->orderByDesc('commission_runs.year')
            ->orderByDesc('commission_runs.month')
            ->get();

        return view('affiliate.dashboard', [
            'affiliate' => $affiliate,
            'personalSales' => $personalSales,
            'commissionSummary' => $commissionSummary,
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
            'directDownlines' => $affiliate->directDownlines()
                ->select(['id', 'upline_id', 'name', 'email', 'affiliate_code', 'affiliate_type', 'status'])
                ->with([
                    'tiktokAccounts:id,affiliate_id,username,username_normalized,status',
                ])
                ->withCount('tiktokAccounts')
                ->orderBy('name')
                ->get(),
            'recentOrders' => $affiliate->tiktokOrders()
                ->select(['id', 'affiliate_id', 'order_id', 'creator_username', 'order_status', 'estimated_commission_base', 'time_created'])
                ->latest('time_created')
                ->latest()
                ->limit(50)
                ->get(),
            'commissionEntries' => $commissionEntries,
            'commissionSourceOptions' => $commissionSourceOptions,
            'commissionPeriodOptions' => $commissionPeriodOptions,
            'commissionFilters' => [
                'commission_type' => $commissionType,
                'source_affiliate' => $sourceAffiliate,
                'commission_period' => $period,
            ],
            'entryTypeLabels' => $entryTypeLabels,
        ]);
    }

    private function sourceAffiliateBelongsToReceivedEntries(int $receiverAffiliateId, int $sourceAffiliateId): bool
    {
        return DB::table('commission_entries')
            ->where('receiver_affiliate_id', $receiverAffiliateId)
            ->where('source_affiliate_id', $sourceAffiliateId)
            ->exists();
    }

    private function totalTeamSize(int $affiliateId): int
    {
        $rows = DB::table('affiliates')
            ->select(['id', 'upline_id'])
            ->whereNotNull('upline_id')
            ->get()
            ->groupBy('upline_id');
        $queue = collect($rows->get($affiliateId, []))->pluck('id')->all();
        $count = 0;

        while ($queue !== []) {
            $currentId = array_shift($queue);
            $count++;

            foreach ($rows->get($currentId, []) as $child) {
                $queue[] = $child->id;
            }
        }

        return $count;
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
}
