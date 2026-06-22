@php
    $affiliate = $node['affiliate'];
    $children = $node['children'];
    $hasChildren = $children->isNotEmpty();
    $currentAncestors = trim(($ancestorIds ?? '').','.$affiliate->id, ',');
    $depth = $depth ?? 0;
    $isRoot = $depth === 0;
    $childrenCollapsed = $depth >= 1;
    $position = $node['direct_count'] > 0 ? 'Manager' : 'Affiliate';
    $loginAccess = $affiliate->user ? ($affiliate->affiliate_code ?: $affiliate->user->email) : 'No login access';
    $tiktokAccounts = $affiliate->tiktokAccounts->pluck('username_normalized')->values();
    $directCount = $node['direct_count'];
    $level2Count = $node['level2_count'];
    $level3Count = $node['level3_count'];
    $totalTeamCount = $node['total_team_count'];
    $nodeDetail = [
        'name' => $affiliate->name,
        'affiliate_code' => $affiliate->affiliate_code ?? '-',
        'group_name' => $affiliate->group_name ?? '-',
        'affiliate_type' => $affiliate->affiliate_type === 'external'
            ? 'Affiliate Luar'
            : 'Inhouse',
        'position' => $position,
        'status' => ucfirst($affiliate->status),
        'direct_upline' => $affiliate->upline?->name ?? '-',
        'direct_downlines' => $affiliate->directDownlines
            ->map(fn ($downline) => [
                'name' => $downline->name,
                'affiliate_code' => $downline->affiliate_code ?? '-',
            ])
            ->values()
            ->all(),
        'tiktok_accounts' => $affiliate->tiktokAccounts
            ->pluck('username')
            ->values()
            ->all(),
        'direct_count' => $directCount,
        'level_2_count' => $level2Count,
        'level_3_count' => $level3Count,
        'level2_count' => $level2Count,
        'level3_count' => $level3Count,
        'total_team_count' => $totalTeamCount,
        'login_access' => $loginAccess,
        'url' => route('admin.affiliates.show', $affiliate),
    ];
@endphp

<div data-hierarchy-node
    data-node-id="{{ $affiliate->id }}"
    data-parent-id="{{ $parentId ?? '' }}"
    data-ancestor-ids="{{ $ancestorIds ?? '' }}"
    data-node-name="{{ strtolower($affiliate->name) }}"
    data-node-code="{{ strtolower($affiliate->affiliate_code ?? '') }}"
    data-node-group="{{ $affiliate->group_name ?? '' }}"
    data-node-type="{{ $affiliate->affiliate_type ?? 'inhouse' }}"
    data-node-tiktok="{{ strtolower($tiktokAccounts->implode(' ')) }}"
    data-node-status="{{ $affiliate->status }}"
    data-node-position="{{ strtolower($position) }}"
    data-node-depth="{{ $depth }}"
    data-node-detail='@json($nodeDetail)'
    class="min-w-[24rem]">
    <div class="rounded-lg border {{ $isRoot ? 'border-emerald-200 bg-white shadow-sm' : 'border-slate-200 bg-white' }} px-3 py-2 transition hover:border-emerald-200 hover:shadow-sm">
        <div class="flex items-start gap-3">
            <button type="button"
                data-hierarchy-toggle="{{ $affiliate->id }}"
                class="{{ $hasChildren ? '' : 'invisible' }} mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-sm font-black text-slate-600 hover:bg-slate-100"
                aria-expanded="{{ $childrenCollapsed ? 'false' : 'true' }}">
                {{ $childrenCollapsed ? '+' : '-' }}
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" data-open-node-detail class="truncate text-left text-sm font-black {{ $isRoot ? 'text-emerald-800' : 'text-slate-950' }} hover:text-emerald-700">
                        {{ $affiliate->name }}
                    </button>
                    <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-600">
                        {{ $affiliate->affiliate_code ?: '-' }}
                    </span>
                    <span class="badge badge-blue">{{ $affiliate->group_name ?: '-' }}</span>
                    <span class="badge {{ $affiliate->affiliate_type === 'external' ? 'badge-amber' : 'badge-teal' }}">
                        {{ $affiliate->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                    </span>
                    <span class="badge {{ $position === 'Manager' ? 'badge-green' : 'badge-gray' }}">
                        {{ $position }}
                    </span>
                    <span class="badge {{ $affiliate->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                        {{ ucfirst($affiliate->status) }}
                    </span>
                </div>
                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $loginAccess }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Direct {{ $directCount }} &middot; L2 {{ $level2Count }} &middot; L3 {{ $level3Count }} &middot; Total {{ $totalTeamCount }}
                </p>
            </div>
        </div>
    </div>

    @if ($hasChildren)
        <div data-hierarchy-children="{{ $affiliate->id }}" class="{{ $childrenCollapsed ? 'hidden' : '' }} ml-5 mt-2 space-y-2 border-l border-slate-200 pl-4">
            @foreach ($children as $childNode)
                @include('admin.affiliates.partials.hierarchy-node', [
                    'node' => $childNode,
                    'parentId' => $affiliate->id,
                    'ancestorIds' => $currentAncestors,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </div>
    @endif
</div>
