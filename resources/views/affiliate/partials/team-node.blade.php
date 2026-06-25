@php
    $member = $node['affiliate'];
    $children = $node['children'];
    $depth = $node['depth'];
    $isRoot = $depth === 0;
    $hasChildren = $children->isNotEmpty();
    $childrenCollapsed = $depth >= 1;
    $levelLabel = $isRoot ? 'Root' : 'L'.$depth;
    $currentAncestors = trim(($ancestorIds ?? '').','.$member->id, ',');
    $accounts = $member->tiktokAccounts;
    $visibleAccounts = $accounts->take(2);
    $moreAccounts = max(0, $accounts->count() - 2);
    $theme = match (true) {
        $isRoot => [
            'border' => 'border-emerald-300 border-l-emerald-600',
            'label' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'name' => 'text-emerald-900',
        ],
        $depth === 1 => [
            'border' => 'border-blue-200 border-l-blue-500',
            'label' => 'border-blue-200 bg-blue-50 text-blue-700',
            'name' => 'text-slate-950',
        ],
        $depth === 2 => [
            'border' => 'border-amber-200 border-l-amber-500',
            'label' => 'border-amber-200 bg-amber-50 text-amber-800',
            'name' => 'text-slate-950',
        ],
        default => [
            'border' => 'border-slate-200 border-l-slate-400',
            'label' => 'border-slate-200 bg-slate-100 text-slate-700',
            'name' => 'text-slate-950',
        ],
    };
@endphp

<div
    data-team-node
    data-node-id="{{ $member->id }}"
    data-parent-id="{{ $parentId ?? '' }}"
    data-ancestor-ids="{{ $ancestorIds ?? '' }}"
    data-search="{{ strtolower(trim($member->name.' '.$member->affiliate_code.' '.$accounts->pluck('username_normalized')->implode(' '))) }}"
    data-depth="{{ $depth }}"
    class="relative min-w-0"
>
    <div data-team-node-card class="rounded-lg border border-l-4 {{ $theme['border'] }} bg-white {{ $isRoot ? 'p-4 shadow-sm ring-1 ring-emerald-100' : 'p-3' }} transition hover:shadow-md">
        <div class="flex items-start gap-2.5">
            <button
                type="button"
                data-team-toggle="{{ $member->id }}"
                class="{{ $hasChildren ? '' : 'invisible' }} mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                aria-expanded="{{ $childrenCollapsed ? 'false' : 'true' }}"
                aria-label="{{ $childrenCollapsed ? 'Expand' : 'Collapse' }} {{ $member->name }}"
            >
                <span data-team-chevron class="h-2.5 w-2.5 border-b-2 border-r-2 border-current transition-transform {{ $childrenCollapsed ? '-rotate-45' : 'rotate-45 -translate-y-0.5' }}"></span>
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-black uppercase {{ $theme['label'] }}">{{ $levelLabel }}</span>
                    <span class="whitespace-normal break-words text-sm font-black leading-snug {{ $theme['name'] }}">{{ $member->name }}</span>
                    <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-600">{{ $member->affiliate_code ?: '-' }}</span>
                    <span class="badge {{ $member->affiliate_type === 'external' ? 'badge-amber' : 'badge-teal' }}">
                        {{ $member->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                    </span>
                    <span class="badge {{ $member->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($member->status) }}</span>
                </div>

                <p class="mt-1 text-xs font-semibold text-slate-500">
                    {{ number_format($accounts->count()) }} TikTok &middot;
                    {{ number_format($node['direct_count']) }} direct &middot;
                    {{ number_format($node['total_team_count']) }} total
                </p>

                @if ($visibleAccounts->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($visibleAccounts as $account)
                            <span class="inline-flex max-w-full rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700">
                                <span class="break-all">{{ $account->username_normalized }}</span>
                            </span>
                        @endforeach
                        @if ($moreAccounts > 0)
                            <span class="inline-flex rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500">+{{ $moreAccounts }} more</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($hasChildren)
        <div data-team-children="{{ $member->id }}" class="{{ $childrenCollapsed ? 'hidden' : '' }} relative ml-3 mt-2 space-y-2 border-l-2 border-slate-200 pl-5 sm:ml-5 sm:pl-7">
            @foreach ($children as $childNode)
                <div class="relative before:absolute before:-left-5 before:top-5 before:h-px before:w-5 before:bg-slate-300 sm:before:-left-7 sm:before:w-7">
                    @include('affiliate.partials.team-node', [
                        'node' => $childNode,
                        'parentId' => $member->id,
                        'ancestorIds' => $currentAncestors,
                    ])
                </div>
            @endforeach
        </div>
    @endif
</div>
