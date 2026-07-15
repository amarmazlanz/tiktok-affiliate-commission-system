@php
    $money ??= fn ($value) => 'RM '.number_format((float) $value, 2);
    $summarySortUrl = function (string $key) use ($filters, $commission): string {
        $currentSort = $filters['summary_sort'] ?? 'affiliate';
        $currentDirection = $filters['summary_dir'] ?? 'asc';
        $nextDirection = $currentSort === $key && $currentDirection === 'asc' ? 'desc' : 'asc';
        if ($currentSort !== $key && $key !== 'affiliate') {
            $nextDirection = 'desc';
        }
        $params = array_merge(
            request()->except(['ajax', 'section', 'summary_sort', 'summary_dir', 'summary_page']),
            ['summary_sort' => $key, 'summary_dir' => $nextDirection, 'summary_page' => 1],
        );
        return route('admin.commissions.show', $commission).'?'.http_build_query($params);
    };
    $summarySortIcon = fn (string $key) => ($filters['summary_sort'] ?? 'affiliate') === $key
        ? (($filters['summary_dir'] ?? 'asc') === 'asc' ? '↑' : '↓')
        : '';
@endphp

<div class="flex flex-wrap items-center gap-4 border-b border-slate-100 bg-white px-6 py-3">
    <p class="text-sm text-slate-500">
        Showing {{ number_format($summaries->firstItem() ?? 0) }}-{{ number_format($summaries->lastItem() ?? 0) }} of {{ number_format($summaries->total()) }} affiliates
    </p>
    <div class="inline-flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-1.5">
        <span class="text-xs font-bold uppercase tracking-wide text-emerald-700">
            {{ ($filters['summary_group'] ?? '') !== '' ? 'Group Total Sales' : 'Filtered Total Sales' }}
        </span>
        <span class="text-sm font-black text-emerald-800">{{ $money($filteredSummarySalesTotal) }}</span>
    </div>
</div>

<div class="hidden lg:block">
    <div class="max-h-[430px] overflow-y-auto">
        <table class="w-full table-fixed divide-y divide-slate-200 text-[13px]">
            <colgroup>
                <col class="w-[20%]">
                <col class="w-[10%]">
                <col class="w-[9%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
                <col class="w-[9%]">
                <col class="w-[9%]">
                <col class="w-[11%]">
                <col class="w-[12%]">
            </colgroup>
            <thead class="sticky top-0 z-10 bg-slate-100 shadow-sm">
                <tr>
                    <th class="px-3 py-3 text-left font-bold text-slate-700"><a href="{{ $summarySortUrl('affiliate') }}" data-summary-sort>Affiliate {{ $summarySortIcon('affiliate') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('total_sales') }}" data-summary-sort>Total Sales {{ $summarySortIcon('total_sales') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('personal') }}" data-summary-sort>Personal {{ $summarySortIcon('personal') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('manager_bonus') }}" data-summary-sort>Manager Bonus {{ $summarySortIcon('manager_bonus') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('l1_earnings') }}" data-summary-sort>Level 1 Earnings {{ $summarySortIcon('l1_earnings') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('l2_earnings') }}" data-summary-sort>Level 2 Earnings {{ $summarySortIcon('l2_earnings') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('l3_earnings') }}" data-summary-sort>Level 3 Earnings {{ $summarySortIcon('l3_earnings') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-teal-700"><a href="{{ $summarySortUrl('total_overriding') }}" data-summary-sort>Total Overriding {{ $summarySortIcon('total_overriding') }}</a></th>
                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('total') }}" data-summary-sort>Total Commission {{ $summarySortIcon('total') }}</a></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($summaries as $summary)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-normal break-words px-3 py-3 leading-snug">
                            <span class="font-medium text-slate-950">{{ $summary->affiliate_name }}</span>
                            @if ($summary->affiliate_type === 'online')
                                <span class="mt-1 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Online</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">{{ $money($summary->total_sales) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">{{ $money($summary->personal) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">{{ $money($summary->manager_bonus) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">{{ $money($summary->l1_earnings) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">{{ $money($summary->l2_earnings) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">{{ $money($summary->l3_earnings) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right font-semibold text-teal-700">{{ $money($summary->total_overriding) }}</td>
                        <td class="whitespace-nowrap px-3 py-3 text-right font-bold text-slate-950">{{ $money($summary->total) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-10 text-center text-slate-500">No affiliate found for the current filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="max-h-[430px] space-y-3 overflow-y-auto p-4 lg:hidden">
    @forelse ($summaries as $summary)
        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="break-words text-sm font-bold leading-snug text-slate-950">{{ $summary->affiliate_name }}</h3>
            @if ($summary->affiliate_type === 'online')
                <span class="mt-2 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Online</span>
            @endif
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div><dt class="text-xs font-bold uppercase text-slate-500">Sales</dt><dd class="whitespace-nowrap font-semibold text-slate-900">{{ $money($summary->total_sales) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Total</dt><dd class="whitespace-nowrap font-bold text-emerald-700">{{ $money($summary->total) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Personal</dt><dd class="whitespace-nowrap text-slate-700">{{ $money($summary->personal) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Manager</dt><dd class="whitespace-nowrap text-slate-700">{{ $money($summary->manager_bonus) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Level 1</dt><dd class="whitespace-nowrap text-slate-700">{{ $money($summary->l1_earnings) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Level 2</dt><dd class="whitespace-nowrap text-slate-700">{{ $money($summary->l2_earnings) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Level 3</dt><dd class="whitespace-nowrap text-slate-700">{{ $money($summary->l3_earnings) }}</dd></div>
                <div><dt class="text-xs font-bold uppercase text-slate-500">Total Overriding</dt><dd class="whitespace-nowrap font-semibold text-teal-700">{{ $money($summary->total_overriding) }}</dd></div>
            </dl>
        </article>
    @empty
        <div class="px-5 py-10 text-center text-sm text-slate-500">No affiliate found for the current filter.</div>
    @endforelse
</div>

<div class="border-t border-slate-100 bg-slate-50 px-6 py-3" data-summary-pagination>
    <p class="text-xs text-slate-500">Level 1 Earnings includes normal L1 overriding and qualified L1 split earnings.</p>
    <div class="mt-3">{{ $summaries->links() }}</div>
</div>
