<?php

namespace App\Services;

use App\Models\Affiliate;
use Illuminate\Support\Facades\DB;

class AffiliateTeamService
{
    public function summary(Affiliate $root): array
    {
        $rows = collect($this->branchRows($root->id));
        $depths = $rows->pluck('depth')->map(fn ($depth): int => (int) $depth);

        $descendantIds = $rows
            ->filter(fn ($r) => (int) $r->depth > 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $typeById = $descendantIds
            ? Affiliate::whereKey($descendantIds)->pluck('affiliate_type', 'id')->all()
            : [];

        $depthById = $rows
            ->filter(fn ($r) => (int) $r->depth > 0)
            ->mapWithKeys(fn ($r) => [(int) $r->id => (int) $r->depth])
            ->all();

        $levelBreakdown = [
            1 => ['inhouse' => 0, 'online' => 0],
            2 => ['inhouse' => 0, 'online' => 0],
            3 => ['inhouse' => 0, 'online' => 0],
        ];

        foreach ($descendantIds as $id) {
            $depth = $depthById[$id] ?? 1;
            $type  = $typeById[$id] ?? 'inhouse';
            $key   = min($depth, 3);
            $levelBreakdown[$key][$type]++;
        }

        $inhouseCount = array_sum(array_column($levelBreakdown, 'inhouse'));
        $onlineCount  = array_sum(array_column($levelBreakdown, 'online'));

        return [
            'direct_count'       => $depths->filter(fn ($depth) => $depth === 1)->count(),
            'total_count'        => $depths->filter(fn ($depth) => $depth > 0)->count(),
            'level_2_count'      => $depths->filter(fn ($depth) => $depth === 2)->count(),
            'level_3_plus_count' => $depths->filter(fn ($depth) => $depth >= 3)->count(),
            'inhouse_count'      => $inhouseCount,
            'online_count'       => $onlineCount,
            'level_breakdown'    => [
                ['label' => 'L1 (Direct)', 'inhouse' => $levelBreakdown[1]['inhouse'], 'online' => $levelBreakdown[1]['online']],
                ['label' => 'L2',          'inhouse' => $levelBreakdown[2]['inhouse'], 'online' => $levelBreakdown[2]['online']],
                ['label' => 'L3+',         'inhouse' => $levelBreakdown[3]['inhouse'], 'online' => $levelBreakdown[3]['online']],
            ],
        ];
    }

    public function groupSales(Affiliate $root, int $month, int $year): array
    {
        $descendantIds = collect($this->branchRows($root->id))
            ->filter(fn ($r) => (int) $r->depth > 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($descendantIds)) {
            return ['group_total' => 0.0, 'inhouse_sales' => 0.0, 'online_sales' => 0.0];
        }

        $results = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->join('affiliates', 'affiliates.id', '=', 'commission_entries.source_affiliate_id')
            ->whereIn('commission_entries.source_affiliate_id', $descendantIds)
            ->where('commission_entries.commission_type', 'personal')
            ->where('commission_runs.month', $month)
            ->where('commission_runs.year', $year)
            ->selectRaw('affiliates.affiliate_type, SUM(commission_entries.base_amount) as total_sales')
            ->groupBy('affiliates.affiliate_type')
            ->get()
            ->keyBy('affiliate_type');

        $inhouse = (float) ($results->get('inhouse')?->total_sales ?? 0);
        $online  = (float) ($results->get('online')?->total_sales ?? 0);

        return [
            'group_total'   => $inhouse + $online,
            'inhouse_sales' => $inhouse,
            'online_sales'  => $online,
        ];
    }

    public function tree(Affiliate $root): array
    {
        $depthById = collect($this->branchRows($root->id))
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
        $buildNode = function (Affiliate $affiliate, array $ancestors = []) use (&$buildNode, $childrenByParent, $depthById): array {
            if (in_array($affiliate->id, $ancestors, true)) {
                return $this->emptyNode($affiliate, $depthById[$affiliate->id] ?? 0);
            }

            $children = ($childrenByParent->get($affiliate->id) ?? collect())
                ->map(fn (Affiliate $child): array => $buildNode($child, [...$ancestors, $affiliate->id]))
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

    public function performance(Affiliate $root, int|string $month, int|string $year): array
    {
        $descendantDepths = collect($this->branchRows($root->id))
            ->filter(fn ($row): bool => (int) $row->depth > 0)
            ->unique('id')
            ->mapWithKeys(fn ($row): array => [(int) $row->id => (int) $row->depth]);

        if ($descendantDepths->isEmpty()) {
            return [];
        }

        $descendantIds = $descendantDepths->keys()->all();

        $members = Affiliate::query()
            ->select(['id', 'upline_id', 'name', 'affiliate_code', 'affiliate_type', 'status'])
            ->withCount('tiktokAccounts')
            ->whereKey($descendantIds)
            ->get()
            ->keyBy('id');

        $overridingTypes = ['manager_bonus', 'l1_overriding', 'l1_split_seller', 'l1_split_upline', 'l2_overriding', 'l3_overriding'];

        $personalQuery = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->whereIn('commission_entries.source_affiliate_id', $descendantIds)
            ->where('commission_entries.commission_type', 'personal');
        $this->applyPeriod($personalQuery, $month, $year);
        $personalBySource = $personalQuery
            ->selectRaw('commission_entries.source_affiliate_id as id, SUM(commission_entries.base_amount) as total_sales, SUM(commission_entries.commission_amount) as personal_commission')
            ->groupBy('commission_entries.source_affiliate_id')
            ->get()
            ->keyBy('id');

        $overridingQuery = DB::table('commission_entries')
            ->join('commission_runs', 'commission_runs.id', '=', 'commission_entries.commission_run_id')
            ->where('commission_entries.receiver_affiliate_id', $root->id)
            ->whereIn('commission_entries.source_affiliate_id', $descendantIds)
            ->whereIn('commission_entries.commission_type', $overridingTypes);
        $this->applyPeriod($overridingQuery, $month, $year);
        $overridingBySource = $overridingQuery
            ->selectRaw('commission_entries.source_affiliate_id as id, SUM(commission_entries.commission_amount) as overriding_generated')
            ->groupBy('commission_entries.source_affiliate_id')
            ->get()
            ->keyBy('id');

        $ownSales = [];
        foreach ($descendantIds as $id) {
            $ownSales[$id] = (float) ($personalBySource->get($id)->total_sales ?? 0);
        }

        $rows = [];

        foreach ($descendantIds as $id) {
            $affiliate = $members->get($id);

            if (! $affiliate) {
                continue;
            }

            $depth = $descendantDepths->get($id, 1);

            $rows[] = [
                'affiliate' => $affiliate,
                'level' => $depth === 1 ? 'L1' : ($depth === 2 ? 'L2' : 'L3+'),
                'level_depth' => $depth,
                'tiktok_account_count' => $affiliate->tiktok_accounts_count,
                'total_sales' => $ownSales[$id] ?? 0.0,
                'personal_commission' => (float) ($personalBySource->get($id)->personal_commission ?? 0),
                'overriding_generated' => (float) ($overridingBySource->get($id)->overriding_generated ?? 0),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['level_depth'] <=> $b['level_depth'] ?: strcmp($a['affiliate']->name, $b['affiliate']->name));

        return $rows;
    }

    private function applyPeriod($query, int|string $month, int|string $year): void
    {
        if ($year !== 'all') {
            $query->where('commission_runs.year', $year);
        }

        if ($month !== 'all') {
            $query->where('commission_runs.month', $month);
        }
    }

    private function branchRows(int $rootId): array
    {
        return DB::select(
            <<<'SQL'
                WITH RECURSIVE affiliate_team(id, depth) AS (
                    SELECT id, 0 FROM affiliates WHERE id = ?
                    UNION ALL
                    SELECT affiliates.id, affiliate_team.depth + 1
                    FROM affiliates
                    INNER JOIN affiliate_team ON affiliates.upline_id = affiliate_team.id
                    WHERE affiliate_team.depth < 100
                )
                SELECT id, depth FROM affiliate_team
            SQL,
            [$rootId],
        );
    }

    private function emptyNode(Affiliate $affiliate, int $depth): array
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
}
