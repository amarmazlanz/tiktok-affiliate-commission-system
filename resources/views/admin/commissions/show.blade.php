@extends('layouts.auth')

@section('title', 'Commission Report')

@section('content')
    @php
        $money = fn ($value) => 'RM '.number_format((float) $value, 2);
        $summarySortUrl = function (string $key) use ($filters) {
            $currentSort = $filters['summary_sort'] ?? 'affiliate';
            $currentDirection = $filters['summary_dir'] ?? 'asc';
            $nextDirection = $currentSort === $key && $currentDirection === 'asc' ? 'desc' : 'asc';

            if ($currentSort !== $key && $key !== 'affiliate') {
                $nextDirection = 'desc';
            }

            return request()->fullUrlWithQuery([
                'summary_sort' => $key,
                'summary_dir' => $nextDirection,
                'summary_page' => 1,
            ]);
        };
        $summarySortIcon = fn (string $key) => ($filters['summary_sort'] ?? 'affiliate') === $key
            ? (($filters['summary_dir'] ?? 'asc') === 'asc' ? '^' : 'v')
            : '';
    @endphp

    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Commission Report</h1>
                </div>
                <a href="{{ route('admin.commissions.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Commission Runs
                </a>
            </div>
        </header>

        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="stat-card">
                    <p class="stat-label">Period</p>
                    <p class="stat-value">{{ $months[$commission->month] }} {{ $commission->year }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Sales</p>
                    <p class="stat-value stat-value-money">{{ $money($totalImportedSales) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Mapped Affiliate Sales</p>
                    <p class="stat-value stat-value-money">{{ $money($mappedAffiliateSalesTotal) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">No Upline Sales</p>
                    <p class="stat-value text-amber-700">{{ $money($noUplineTotals['total_sales']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Overriding</p>
                    <p class="stat-value text-teal-700">{{ $money($totalOverriding) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Commission</p>
                    <p class="stat-value stat-value-money">{{ $money($commission->total_commission) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">No Upline Orders</p>
                    <p class="stat-value text-amber-700">{{ number_format($noUplineTotals['order_count']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">No Upline TikTok Accounts</p>
                    <p class="stat-value text-amber-700">{{ number_format($noUplineTotals['username_count']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Status</p>
                    <p class="mt-3">
                        <span class="badge {{ in_array($commission->status, ['completed', 'final'], true) ? 'badge-green' : ($commission->status === 'processing' ? 'badge-blue' : ($commission->status === 'failed' ? 'badge-red' : 'badge-amber')) }}">
                            {{ str($commission->status)->headline() }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="space-y-3">
                            <h2 class="text-lg font-semibold text-slate-950">Affiliate Income Summary</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Showing {{ number_format($summaries->firstItem() ?? 0) }}-{{ number_format($summaries->lastItem() ?? 0) }} of {{ number_format($summaries->total()) }} affiliates
                            </p>
                            <div class="inline-flex items-center gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2">
                                <span class="text-xs font-bold uppercase tracking-wide text-emerald-700">
                                    {{ ($filters['summary_group'] ?? '') !== '' ? 'Group Total Sales' : 'Filtered Total Sales' }}
                                </span>
                                <span class="text-sm font-black text-emerald-800">{{ $money($filteredSummarySalesTotal) }}</span>
                            </div>
                        </div>
                        <form method="GET" action="{{ route('admin.commissions.show', $commission) }}" class="grid w-full gap-2 sm:grid-cols-[220px_minmax(0,1fr)_auto] lg:w-auto" data-auto-filter-form>
                            @foreach (request()->except(['summary_search', 'summary_group', 'summary_affiliate', 'summary_page']) as $key => $value)
                                @if (is_scalar($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <div>
                                <label for="summary_group" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Group</label>
                                <select id="summary_group" name="summary_group" class="form-field" data-auto-submit data-clear-target="summary_affiliate">
                                    <option value="">All Groups</option>
                                    @foreach ($summaryGroupOptions as $groupName)
                                        <option value="{{ $groupName }}" @selected(($filters['summary_group'] ?? '') === $groupName)>{{ $groupName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0">
                                <label for="summary_affiliate_search" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Affiliate</label>
                                <div class="relative" data-combobox>
                                    <input id="summary_affiliate" name="summary_affiliate" type="hidden" value="{{ $filters['summary_affiliate'] }}" data-combobox-value>
                                    <div class="mt-2 flex overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100">
                                        <input
                                            id="summary_affiliate_search"
                                            type="text"
                                            autocomplete="off"
                                            placeholder="All Affiliates"
                                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none"
                                            data-combobox-input
                                        >
                                        <button type="button" class="border-l border-slate-200 px-3 text-xs font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-900" data-combobox-clear>
                                            Clear
                                        </button>
                                    </div>
                                    <div class="absolute z-30 mt-2 hidden max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg" data-combobox-options>
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800" data-combobox-option data-id="" data-label="All Affiliates" data-search="all affiliates">
                                            All Affiliates
                                        </button>
                                        @foreach ($summaryAffiliateOptions as $affiliateOption)
                                            @php
                                                $affiliateLabel = trim($affiliateOption->name.' '.($affiliateOption->affiliate_code ? '('.$affiliateOption->affiliate_code.')' : ''));
                                                $affiliateSearch = strtolower(trim($affiliateOption->name.' '.$affiliateOption->affiliate_code));
                                            @endphp
                                            <button
                                                type="button"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-emerald-50"
                                                data-combobox-option
                                                data-id="{{ $affiliateOption->id }}"
                                                data-label="{{ $affiliateLabel }}"
                                                data-search="{{ $affiliateSearch }}"
                                            >
                                                <span class="block font-semibold text-slate-900">{{ $affiliateOption->name }}</span>
                                                @if ($affiliateOption->affiliate_code)
                                                    <span class="block text-xs text-slate-500">{{ $affiliateOption->affiliate_code }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                        <p class="hidden px-3 py-3 text-sm text-slate-500" data-combobox-empty>No affiliate found</p>
                                    </div>
                                </div>
                            </div>
                            <a href="{{ route('admin.commissions.show', array_merge(['commission' => $commission], request()->except(['summary_search', 'summary_group', 'summary_affiliate', 'summary_sort', 'summary_dir', 'summary_page']))) }}" class="btn-secondary self-end">Clear</a>
                        </form>
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
                                    <th class="px-3 py-3 text-left font-bold text-slate-700"><a href="{{ $summarySortUrl('affiliate') }}">Affiliate {{ $summarySortIcon('affiliate') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('total_sales') }}">Total Sales {{ $summarySortIcon('total_sales') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('personal') }}">Personal {{ $summarySortIcon('personal') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('manager_bonus') }}">Manager Bonus {{ $summarySortIcon('manager_bonus') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('l1_earnings') }}">Level 1 Earnings {{ $summarySortIcon('l1_earnings') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('l2_earnings') }}">Level 2 Earnings {{ $summarySortIcon('l2_earnings') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('l3_earnings') }}">Level 3 Earnings {{ $summarySortIcon('l3_earnings') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-teal-700"><a href="{{ $summarySortUrl('total_overriding') }}">Total Overriding {{ $summarySortIcon('total_overriding') }}</a></th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700"><a href="{{ $summarySortUrl('total') }}">Total Commission {{ $summarySortIcon('total') }}</a></th>
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
                                        <td colspan="9" class="px-5 py-10 text-center text-slate-500">
                                            No affiliate found for the current search.
                                        </td>
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
                        <div class="px-5 py-10 text-center text-sm text-slate-500">
                            No affiliate found for the current search.
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-slate-100 bg-slate-50 px-6 py-3">
                    <p class="text-xs text-slate-500">Level 1 Earnings includes normal L1 overriding and qualified L1 split earnings.</p>
                    <div class="mt-3">{{ $summaries->links() }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">No Upline Sales Monitoring</h2>
                            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                                No Upline sales are sales from TikTok usernames that are not currently linked to an internal affiliate/upline profile. These sales are shown for monitoring only and do not generate overriding commission.
                            </p>
                        </div>
                        <span class="badge badge-amber">Monitoring Only</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">TikTok Username</th>
                                <th class="text-right">Order Count</th>
                                <th class="text-right">Total Sales</th>
                                <th class="text-right">TikTok Commission Paid</th>
                                <th class="text-left">First Sale</th>
                                <th class="text-left">Last Sale</th>
                                <th class="text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($noUplineSalesRows as $row)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $row->creator_username ?: $row->creator_username_normalized }}</td>
                                    <td class="money text-slate-700">{{ number_format((int) $row->order_count) }}</td>
                                    <td class="money font-semibold text-slate-950">{{ $money($row->total_sales) }}</td>
                                    <td class="money text-slate-700">{{ $money($row->total_tiktok_commission_paid) }}</td>
                                    <td class="whitespace-nowrap text-slate-700">{{ $row->first_sale_date ? \Carbon\Carbon::parse($row->first_sale_date)->format('d/m/Y') : '-' }}</td>
                                    <td class="whitespace-nowrap text-slate-700">{{ $row->last_sale_date ? \Carbon\Carbon::parse($row->last_sale_date)->format('d/m/Y') : '-' }}</td>
                                    <td><span class="badge badge-amber">No Upline</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-500">No No Upline sales found for this commission period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 bg-slate-50 px-6 py-3">
                    {{ $noUplineSalesRows->links() }}
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Commission Entry Details</h2>
                </div>

                <form method="GET" action="{{ route('admin.commissions.show', $commission) }}" class="border-b border-slate-200 bg-white px-6 py-5" data-entry-filter-form>
                    @foreach (request()->except(['entry_group', 'receiver', 'source', 'type', 'order_id', 'per_page', 'entries_page', 'page']) as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <div class="grid gap-4 lg:grid-cols-[180px_1fr_1fr_1fr_1fr_120px_auto] lg:items-end">
                        <div>
                            <label for="entry_group" class="block text-sm font-semibold text-slate-700">Group</label>
                            <select id="entry_group" name="entry_group" class="form-field" data-auto-submit data-clear-target="receiver,source">
                                <option value="">All Groups</option>
                                @foreach ($summaryGroupOptions as $groupName)
                                    <option value="{{ $groupName }}" @selected(($filters['entry_group'] ?? '') === $groupName)>{{ $groupName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="receiver" class="block text-sm font-semibold text-slate-700">Receiver</label>
                            @include('admin.commissions.partials.receiver-combobox', [
                                'receiverOptions' => $receiverOptions,
                                'selectedReceiver' => $filters['receiver'],
                            ])
                        </div>

                        <div>
                            <label for="source" class="block text-sm font-semibold text-slate-700">Source</label>
                            @include('admin.commissions.partials.source-combobox', [
                                'sourceOptions' => $sourceOptions,
                                'selectedSource' => $filters['source'],
                            ])
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-semibold text-slate-700">Type</label>
                            <select id="type" name="type" class="form-field" data-auto-submit>
                                <option value="">All Types</option>
                                @foreach ($typeOptions as $type)
                                    <option value="{{ $type['value'] }}" @selected($filters['type'] === $type['value'])>{{ $type['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="order_id" class="block text-sm font-semibold text-slate-700">Order ID</label>
                            <input id="order_id" name="order_id" type="search" value="{{ $filters['order_id'] }}" placeholder="Search order ID" class="form-field" data-auto-submit-debounce>
                        </div>

                        <div>
                            <label for="per_page" class="block text-sm font-semibold text-slate-700">Rows</label>
                            <select id="per_page" name="per_page" class="form-field" data-auto-submit>
                                @foreach ([50, 100, 200] as $option)
                                    <option value="{{ $option }}" @selected((int) $filters['per_page'] === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" class="btn-secondary" data-entry-reset>Reset</button>
                        </div>
                    </div>

                    <p class="mt-4 hidden items-center gap-2 text-sm font-semibold text-emerald-700" data-entry-loading>
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-emerald-200 border-t-emerald-700"></span>
                        Loading...
                    </p>
                    <p class="mt-4 hidden text-sm font-semibold text-red-700" data-entry-error>Unable to load results. Please try again.</p>
                </form>

                <div data-entry-results>
                    @include('admin.commissions.partials.entry-results', [
                        'commissionEntries' => $commissionEntries,
                        'entryTypeLabels' => $entryTypeLabels,
                    ])
                </div>
            </div>
        </section>
    </main>

    <script>
        (() => {
            const entryForm = document.querySelector('[data-entry-filter-form]');
            const entryResults = document.querySelector('[data-entry-results]');
            const entryLoading = entryForm?.querySelector('[data-entry-loading]');
            const entryError = entryForm?.querySelector('[data-entry-error]');
            let activeRequest = null;

            const entryParameterNames = ['entry_group', 'receiver', 'source', 'type', 'order_id', 'per_page', 'entries_page'];

            const setLoading = (loading) => {
                entryLoading?.classList.toggle('hidden', ! loading);
                entryLoading?.classList.toggle('flex', loading);
                entryError?.classList.add('hidden');
                entryResults?.classList.toggle('opacity-60', loading);
                entryForm?.querySelectorAll('select, input, button').forEach((control) => {
                    control.disabled = loading;
                });
            };

            const browserUrl = (requestUrl) => {
                const url = new URL(requestUrl, window.location.href);
                url.searchParams.delete('ajax');
                return `${url.pathname}${url.search}${url.hash}`;
            };

            const replaceReceiverCombobox = (html) => {
                const current = entryForm?.querySelector('[data-entry-receiver-combobox]');
                if (! current || ! html) return;

                current.outerHTML = html;
                initializeCombobox(entryForm.querySelector('[data-entry-receiver-combobox]'));
            };

            const replaceSourceCombobox = (html) => {
                const current = entryForm?.querySelector('[data-entry-source-combobox]');
                if (! current || ! html) return;

                current.outerHTML = html;
                initializeCombobox(entryForm.querySelector('[data-entry-source-combobox]'));
            };

            const fetchEntryResults = async (targetUrl = null, updateHistory = true) => {
                if (! entryForm || ! entryResults) return;

                activeRequest?.abort();
                const requestController = new AbortController();
                activeRequest = requestController;

                const url = targetUrl
                    ? new URL(targetUrl, window.location.href)
                    : new URL(entryForm.action, window.location.href);

                if (! targetUrl) {
                    const formData = new FormData(entryForm);
                    url.search = '';
                    formData.forEach((value, key) => {
                        if (String(value) !== '') url.searchParams.append(key, value);
                    });
                    url.searchParams.delete('entries_page');
                }

                url.searchParams.set('ajax', '1');
                setLoading(true);

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: requestController.signal,
                    });

                    if (! response.ok) throw new Error(`Request failed with status ${response.status}`);

                    const data = await response.json();
                    entryResults.innerHTML = data.html;
                    replaceReceiverCombobox(data.receiverHtml);
                    replaceSourceCombobox(data.sourceHtml);

                    if (updateHistory) {
                        window.history.replaceState({ commissionEntries: true }, '', browserUrl(url));
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') entryError?.classList.remove('hidden');
                } finally {
                    if (activeRequest === requestController) {
                        setLoading(false);
                        activeRequest = null;
                    }
                }
            };

            const submitForm = (form) => {
                if (form?.matches('[data-entry-filter-form]')) {
                    fetchEntryResults();
                    return;
                }

                if (form?.requestSubmit) {
                    form.requestSubmit();
                    return;
                }

                form?.submit();
            };

            const initializeCombobox = (root) => {
                if (! root || root.dataset.comboboxReady === 'true') return;
                root.dataset.comboboxReady = 'true';

                const hiddenInput = root.querySelector('[data-combobox-value]');
                const searchInput = root.querySelector('[data-combobox-input]');
                const optionsPanel = root.querySelector('[data-combobox-options]');
                const emptyState = root.querySelector('[data-combobox-empty]');
                const clearButton = root.querySelector('[data-combobox-clear]');
                const options = Array.from(root.querySelectorAll('[data-combobox-option]'));
                let activeIndex = -1;

                const visibleOptions = () => options.filter((option) => ! option.classList.contains('hidden'));
                const openPanel = () => optionsPanel.classList.remove('hidden');
                const closePanel = () => {
                    optionsPanel.classList.add('hidden');
                    activeIndex = -1;
                    options.forEach((option) => option.classList.remove('bg-emerald-50', 'text-emerald-800'));
                };
                const setActive = (index) => {
                    const visible = visibleOptions();
                    options.forEach((option) => option.classList.remove('bg-emerald-50', 'text-emerald-800'));

                    if (! visible.length) {
                        activeIndex = -1;
                        return;
                    }

                    activeIndex = (index + visible.length) % visible.length;
                    visible[activeIndex].classList.add('bg-emerald-50', 'text-emerald-800');
                    visible[activeIndex].scrollIntoView({ block: 'nearest' });
                };
                const selectOption = (option) => {
                    hiddenInput.value = option.dataset.id || '';
                    searchInput.value = option.dataset.id ? option.dataset.label : '';
                    searchInput.placeholder = option.dataset.label || searchInput.placeholder;
                    closePanel();
                    submitForm(hiddenInput.form);
                };
                const filterOptions = () => {
                    const term = searchInput.value.trim().toLowerCase();
                    let matchCount = 0;

                    options.forEach((option) => {
                        const matches = term === ''
                            || option.dataset.search.includes(term)
                            || option.dataset.label.toLowerCase().includes(term);
                        option.classList.toggle('hidden', ! matches);
                        if (matches) matchCount += 1;
                    });

                    emptyState.classList.toggle('hidden', matchCount > 0);
                    setActive(0);
                };
                const selectedOption = options.find((option) => option.dataset.id === hiddenInput.value);

                if (selectedOption?.dataset.id) {
                    searchInput.value = selectedOption.dataset.label;
                    searchInput.placeholder = selectedOption.dataset.label;
                }

                searchInput.addEventListener('focus', () => {
                    openPanel();
                    filterOptions();
                });
                searchInput.addEventListener('input', () => {
                    hiddenInput.value = '';
                    openPanel();
                    filterOptions();
                });
                searchInput.addEventListener('keydown', (event) => {
                    const visible = visibleOptions();

                    if (event.key === 'Escape') closePanel();
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        openPanel();
                        setActive(activeIndex + 1);
                    }
                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        openPanel();
                        setActive(activeIndex - 1);
                    }
                    if (event.key === 'Enter' && ! optionsPanel.classList.contains('hidden') && visible[activeIndex]) {
                        event.preventDefault();
                        selectOption(visible[activeIndex]);
                    }
                });
                options.forEach((option) => option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    selectOption(option);
                }));
                clearButton.addEventListener('click', () => {
                    hiddenInput.value = '';
                    searchInput.value = '';
                    searchInput.placeholder = options[0]?.dataset.label || 'All';
                    submitForm(hiddenInput.form);
                });
                document.addEventListener('click', (event) => {
                    if (! root.contains(event.target)) closePanel();
                });
            };

            document.querySelectorAll('[data-combobox]').forEach(initializeCombobox);

            document.querySelectorAll('[data-auto-submit]').forEach((field) => {
                field.addEventListener('change', () => {
                    const clearTarget = field.dataset.clearTarget;

                    if (clearTarget) {
                        clearTarget.split(',').forEach((targetName) => {
                            const target = field.form?.querySelector(`[name="${targetName.trim()}"]`);
                            if (target) target.value = '';
                        });
                    }

                    submitForm(field.form);
                });
            });

            document.querySelectorAll('[data-auto-submit-debounce]').forEach((field) => {
                let timer = null;

                field.addEventListener('input', () => {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(() => submitForm(field.form), 650);
                });
                field.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        window.clearTimeout(timer);
                        submitForm(field.form);
                    }
                });
            });

            entryForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                fetchEntryResults();
            });

            entryForm?.querySelector('[data-entry-reset]')?.addEventListener('click', () => {
                entryParameterNames.forEach((name) => {
                    const field = entryForm.querySelector(`[name="${name}"]`);
                    if (field) field.value = name === 'per_page' ? '50' : '';
                });
                const receiverSearch = entryForm.querySelector('[data-entry-receiver-combobox] [data-combobox-input]');
                const sourceSearch = entryForm.querySelector('[data-entry-source-combobox] [data-combobox-input]');
                if (receiverSearch) receiverSearch.value = '';
                if (sourceSearch) sourceSearch.value = '';
                fetchEntryResults();
            });

            entryResults?.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (! link || ! link.closest('[data-entry-pagination]')) return;

                event.preventDefault();
                fetchEntryResults(link.href);
            });

            window.addEventListener('popstate', () => {
                if (! entryForm) return;

                const parameters = new URL(window.location.href).searchParams;
                entryParameterNames.forEach((name) => {
                    const field = entryForm.querySelector(`[name="${name}"]`);
                    if (field) field.value = parameters.get(name) || (name === 'per_page' ? '50' : '');
                });
                fetchEntryResults(window.location.href, false);
            });
        })();
    </script>
@endsection
