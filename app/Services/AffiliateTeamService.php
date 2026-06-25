<?php

namespace App\Services;

use App\Models\Affiliate;
use Illuminate\Support\Facades\DB;

class AffiliateTeamService
{
    public function summary(Affiliate $root): array
    {
        $depths = collect($this->branchRows($root->id))->pluck('depth')->map(fn ($depth): int => (int) $depth);

        return [
            'direct_count' => $depths->filter(fn ($depth) => $depth === 1)->count(),
            'total_count' => $depths->filter(fn ($depth) => $depth > 0)->count(),
            'level_2_count' => $depths->filter(fn ($depth) => $depth === 2)->count(),
            'level_3_plus_count' => $depths->filter(fn ($depth) => $depth >= 3)->count(),
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
