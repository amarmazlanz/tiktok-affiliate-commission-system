<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateHierarchyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $affiliates = Affiliate::query()
            ->select(['id', 'user_id', 'upline_id', 'affiliate_code', 'group_name', 'affiliate_type', 'name', 'email', 'status'])
            ->with([
                'user:id,email',
                'upline:id,name,affiliate_code',
                'directDownlines:id,upline_id,name,affiliate_code,status',
                'tiktokAccounts:id,affiliate_id,username,username_normalized,status',
            ])
            ->withCount(['directDownlines', 'tiktokAccounts'])
            ->orderBy('name')
            ->get();

        $affiliatesById = $affiliates->keyBy('id');
        $childrenByParent = $affiliates->groupBy(fn (Affiliate $affiliate): int => (int) ($affiliate->upline_id ?? 0));

        $buildNode = function (Affiliate $affiliate, array $ancestorIds = [], int $depth = 0) use (&$buildNode, $childrenByParent): array {
            if (in_array($affiliate->id, $ancestorIds, true)) {
                return [
                    'affiliate' => $affiliate,
                    'children' => collect(),
                    'depth' => $depth,
                    'direct_count' => 0,
                    'level2_count' => 0,
                    'level3_count' => 0,
                    'total_team_count' => 0,
                ];
            }

            $children = ($childrenByParent->get($affiliate->id) ?? collect())
                ->sortBy('name')
                ->values();
            $nextAncestors = [...$ancestorIds, $affiliate->id];
            $childNodes = $children
                ->map(fn (Affiliate $child): array => $buildNode($child, $nextAncestors, $depth + 1))
                ->values();

            $level2Count = $childNodes->sum('direct_count');
            $level3Count = $childNodes->sum('level2_count');

            return [
                'affiliate' => $affiliate,
                'children' => $childNodes,
                'depth' => $depth,
                'direct_count' => $childNodes->count(),
                'level2_count' => $level2Count,
                'level3_count' => $level3Count,
                'total_team_count' => $childNodes->count() + $childNodes->sum('total_team_count'),
            ];
        };

        $rootAffiliates = $affiliates
            ->filter(fn (Affiliate $affiliate): bool => $affiliate->upline_id === null || ! $affiliatesById->has($affiliate->upline_id))
            ->sortBy('name')
            ->values();

        $tree = $rootAffiliates
            ->map(fn (Affiliate $affiliate): array => $buildNode($affiliate))
            ->values();

        $flatten = function ($nodes) use (&$flatten, $affiliatesById) {
            return $nodes->flatMap(function (array $node) use (&$flatten, $affiliatesById) {
                $affiliate = $node['affiliate'];
                $position = $node['direct_count'] > 0 ? 'Manager' : 'Affiliate';
                $row = [[
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'affiliate_code' => $affiliate->affiliate_code ?: '-',
                    'group_name' => $affiliate->group_name ?: '-',
                    'affiliate_type' => $affiliate->affiliate_type ?: 'inhouse',
                    'login_access' => $affiliate->user ? ($affiliate->affiliate_code ?: $affiliate->user->email) : 'No login access',
                    'status' => $affiliate->status,
                    'position' => $position,
                    'direct_upline' => $affiliate->upline_id ? ($affiliatesById->get($affiliate->upline_id)?->name ?? '-') : '-',
                    'direct_downlines' => $affiliate->directDownlines->map(fn (Affiliate $downline): array => [
                        'name' => $downline->name,
                        'affiliate_code' => $downline->affiliate_code ?: '-',
                        'status' => $downline->status,
                    ])->values(),
                    'tiktok_accounts' => $affiliate->tiktokAccounts->pluck('username_normalized')->values(),
                    'direct_count' => $node['direct_count'],
                    'level2_count' => $node['level2_count'],
                    'level3_count' => $node['level3_count'],
                    'total_team_count' => $node['total_team_count'],
                    'depth' => $node['depth'],
                    'url' => route('admin.affiliates.show', $affiliate),
                ]];

                return collect($row)->merge($flatten($node['children']));
            })->values();
        };

        $tableRows = $flatten($tree)
            ->sortBy('name')
            ->values();

        return view('admin.affiliates.hierarchy', [
            'tree' => $tree,
            'totalAffiliates' => $affiliates->count(),
            'totalManagers' => $tableRows->where('position', 'Manager')->count(),
            'maxDepth' => $tableRows->max('depth') !== null ? ((int) $tableRows->max('depth') + 1) : 0,
            'tableRows' => $tableRows,
            'groups' => $affiliates->pluck('group_name')->filter()->unique()->sort()->values(),
        ]);
    }
}
