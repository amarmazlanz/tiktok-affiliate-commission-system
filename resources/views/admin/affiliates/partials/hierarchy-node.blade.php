@php
    $affiliate = $node['affiliate'];
    $children = $node['children'];
    $hasChildren = $children->isNotEmpty();
    $currentAncestors = trim(($ancestorIds ?? '').','.$affiliate->id, ',');
    $depth = $depth ?? 0;
    $isRoot = $depth === 0;
    $childrenCollapsed = $depth >= 1;
    $levelLabel = $isRoot ? 'Root' : 'L'.min($depth, 3);
    $levelTheme = match (true) {
        $isRoot => [
            'border' => 'border-emerald-300',
            'accent' => 'border-l-4 border-l-emerald-600',
            'surface' => 'bg-white',
            'label' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'name' => 'text-emerald-900',
        ],
        $depth === 1 => [
            'border' => 'border-blue-200',
            'accent' => 'border-l-4 border-l-blue-500',
            'surface' => 'bg-white',
            'label' => 'border-blue-200 bg-blue-50 text-blue-700',
            'name' => 'text-slate-950',
        ],
        $depth === 2 => [
            'border' => 'border-amber-200',
            'accent' => 'border-l-4 border-l-amber-500',
            'surface' => 'bg-white',
            'label' => 'border-amber-200 bg-amber-50 text-amber-800',
            'name' => 'text-slate-950',
        ],
        default => [
            'border' => 'border-slate-200',
            'accent' => 'border-l-4 border-l-slate-400',
            'surface' => 'bg-white',
            'label' => 'border-slate-200 bg-slate-100 text-slate-700',
            'name' => 'text-slate-950',
        ],
    };
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
        'affiliate_type' => $affiliate->affiliate_type === 'online'
            ? 'Online'
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
    class="relative min-w-0">
    <div data-node-card class="{{ $levelTheme['surface'] }} {{ $levelTheme['border'] }} {{ $levelTheme['accent'] }} {{ $isRoot ? 'rounded-lg px-4 py-3 shadow-sm ring-1 ring-emerald-100' : 'rounded-md px-3 py-2' }} border transition hover:-translate-y-px hover:shadow-md">
        <div class="flex items-start gap-2.5">
            <button type="button"
                data-hierarchy-toggle="{{ $affiliate->id }}"
                class="{{ $hasChildren ? '' : 'invisible' }} mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                aria-expanded="{{ $childrenCollapsed ? 'false' : 'true' }}"
                aria-label="{{ $childrenCollapsed ? 'Expand' : 'Collapse' }} {{ $affiliate->name }}">
                <span data-chevron class="h-2.5 w-2.5 border-b-2 border-r-2 border-current transition-transform {{ $childrenCollapsed ? '-rotate-45' : 'rotate-45 -translate-y-0.5' }}"></span>
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-black uppercase {{ $levelTheme['label'] }}">
                        {{ $levelLabel }}
                    </span>
                    <button type="button" data-open-node-detail class="min-w-0 whitespace-normal break-words text-left {{ $isRoot ? 'text-base' : 'text-sm' }} font-black leading-snug {{ $levelTheme['name'] }} hover:text-emerald-700 hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-sm">
                        {{ $affiliate->name }}
                    </button>
                    <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-600">
                        {{ $affiliate->affiliate_code ?: '-' }}
                    </span>
                    @if ($isRoot)
                        <span class="badge badge-blue">{{ $affiliate->group_name ?: '-' }}</span>
                    @endif
                    <span class="badge {{ $affiliate->affiliate_type === 'online' ? 'badge-amber' : 'badge-teal' }}">
                        {{ $affiliate->affiliate_type === 'online' ? 'Online' : 'Inhouse' }}
                    </span>
                    <span class="badge {{ $position === 'Manager' ? 'badge-green' : 'badge-gray' }}">
                        {{ $position }}
                    </span>
                    @if ($isRoot)
                        <span class="badge {{ $affiliate->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                            {{ ucfirst($affiliate->status) }}
                        </span>
                    @endif
                </div>
                @if ($isRoot)
                    <p class="mt-1 truncate text-xs text-slate-500">{{ $loginAccess }}</p>
                @endif
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Direct {{ $directCount }} &middot; L2 {{ $level2Count }} &middot; L3 {{ $level3Count }} &middot; Total {{ $totalTeamCount }}
                </p>
            </div>
        </div>
    </div>

    @if ($hasChildren)
        <div data-hierarchy-children="{{ $affiliate->id }}" class="{{ $childrenCollapsed ? 'hidden' : '' }} relative ml-3 mt-2 space-y-2 border-l-2 border-slate-200 pl-5 sm:ml-5 sm:pl-7">
            @foreach ($children as $childNode)
                <div class="relative before:absolute before:-left-5 before:top-5 before:h-px before:w-5 before:bg-slate-300 sm:before:-left-7 sm:before:w-7">
                    @include('admin.affiliates.partials.hierarchy-node', [
                        'node' => $childNode,
                        'parentId' => $affiliate->id,
                        'ancestorIds' => $currentAncestors,
                        'depth' => $depth + 1,
                    ])
                </div>
            @endforeach
        </div>
    @endif
</div>
