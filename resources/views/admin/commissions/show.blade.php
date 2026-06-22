@extends('layouts.auth')

@section('title', 'Commission Report')

@section('content')
    @php
        $commissionEntryRows = $commission->commissionEntries->sortBy('id')->values();
        $receiverOptions = $commissionEntryRows
            ->map(fn ($entry) => $entry->receiverAffiliate?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $sourceOptions = $commissionEntryRows
            ->map(fn ($entry) => $entry->sourceAffiliate?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $typeOptions = $commissionEntryRows
            ->map(fn ($entry) => [
                'value' => $entry->commission_type,
                'label' => $entryTypeLabels[$entry->commission_type] ?? ucfirst(str_replace('_', ' ', $entry->commission_type)),
            ])
            ->unique('value')
            ->sortBy('label')
            ->values();
        $summaryRows = $summaries
            ->map(fn ($summary) => [
                'affiliate' => $summary['affiliate']->name,
                'total_sales' => (float) $summary['total_sales'],
                'personal' => (float) $summary['personal'],
                'manager_bonus' => (float) $summary['manager_bonus'],
                'l1_earnings' => (float) $summary['l1_earnings'],
                'l2_earnings' => (float) $summary['l2_earnings'],
                'l3_earnings' => (float) $summary['l3_earnings'],
                'total' => (float) $summary['total'],
            ])
            ->values();
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

            <div class="grid gap-4 md:grid-cols-4">
                <div class="stat-card">
                    <p class="stat-label">Period</p>
                    <p class="stat-value">{{ $months[$commission->month] }} {{ $commission->year }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Sales</p>
                    <p class="stat-value stat-value-money">RM {{ number_format((float) $commission->total_sales, 2) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Commission</p>
                    <p class="stat-value stat-value-money">RM {{ number_format((float) $commission->total_commission, 2) }}</p>
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
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Affiliate Income Summary</h2>
                            <p id="summary-filter-count" class="mt-1 text-sm text-slate-500">
                                Showing {{ number_format($summaryRows->count()) }} of {{ number_format($summaryRows->count()) }} affiliates
                            </p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                            <div class="min-w-0 sm:w-80">
                                <label for="summary-search" class="sr-only">Search affiliate</label>
                                <input id="summary-search" type="search" placeholder="Search affiliate name" class="form-field">
                            </div>
                            <button type="button" id="summary-search-reset" class="btn-secondary">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <div class="max-h-[430px] overflow-y-auto">
                        <table class="w-full table-fixed divide-y divide-slate-200 text-[13px]">
                            <colgroup>
                                <col class="w-[25%]">
                                <col class="w-[11%]">
                                <col class="w-[10%]">
                                <col class="w-[11%]">
                                <col class="w-[12%]">
                                <col class="w-[10%]">
                                <col class="w-[10%]">
                                <col class="w-[11%]">
                            </colgroup>
                            <thead class="sticky top-0 z-10 bg-slate-100 shadow-sm">
                                <tr>
                                    <th class="px-3 py-3 text-left font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center gap-1 text-left" data-summary-sort="affiliate">Affiliate <span data-sort-icon="affiliate"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="total_sales">Total Sales <span data-sort-icon="total_sales"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="personal">Personal <span data-sort-icon="personal"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="manager_bonus">Manager Bonus <span data-sort-icon="manager_bonus"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="l1_earnings">Level 1 Earnings <span data-sort-icon="l1_earnings"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="l2_earnings">Level 2 Earnings <span data-sort-icon="l2_earnings"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="l3_earnings">Level 3 Earnings <span data-sort-icon="l3_earnings"></span></button>
                                    </th>
                                    <th class="px-3 py-3 text-right font-bold text-slate-700">
                                        <button type="button" class="summary-sort inline-flex items-center justify-end gap-1" data-summary-sort="total">Total Commission <span data-sort-icon="total"></span></button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="summary-table-body" class="divide-y divide-slate-100 bg-white"></tbody>
                        </table>
                    </div>
                </div>

                <div id="summary-card-list" class="max-h-[430px] space-y-3 overflow-y-auto p-4 lg:hidden"></div>

                <template id="summary-empty-template">
                    <div class="px-5 py-10 text-center text-sm text-slate-500">
                        No affiliate found for the current search.
                    </div>
                </template>

                <noscript>
                    <div class="max-h-[430px] overflow-y-auto">
                        <table class="w-full table-fixed divide-y divide-slate-200 text-[13px]">
                            <thead class="sticky top-0 z-10 bg-slate-100 shadow-sm">
                            <tr>
                                <th class="px-3 py-3 text-left font-bold text-slate-700">Affiliate</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Total Sales</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Personal</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Manager Bonus</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Level 1 Earnings</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Level 2 Earnings</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Level 3 Earnings</th>
                                <th class="px-3 py-3 text-right font-bold text-slate-700">Total Commission</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($summaries as $summary)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-normal break-words px-3 py-3 font-medium leading-snug text-slate-950">{{ $summary['affiliate']->name }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">RM {{ number_format((float) $summary['total_sales'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">RM {{ number_format((float) $summary['personal'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">RM {{ number_format((float) $summary['manager_bonus'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">RM {{ number_format((float) $summary['l1_earnings'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">RM {{ number_format((float) $summary['l2_earnings'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">RM {{ number_format((float) $summary['l3_earnings'], 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-bold text-slate-950">RM {{ number_format((float) $summary['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-slate-500">
                                        Tiada commission entry untuk run ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </noscript>
                <div class="border-t border-slate-100 bg-slate-50 px-6 py-3">
                    <p class="text-xs text-slate-500">Level 1 Earnings includes normal L1 overriding and qualified L1 split earnings.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Commission Entry Details</h2>
                </div>

                <div class="border-b border-slate-200 bg-white px-6 py-5">
                    <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                        <div>
                            <label for="entry-filter-receiver" class="block text-sm font-semibold text-slate-700">Receiver</label>
                            <select id="entry-filter-receiver" class="form-field js-entry-filter" data-filter="receiver">
                                <option value="">All Receivers</option>
                                @foreach ($receiverOptions as $receiver)
                                    <option value="{{ $receiver }}">{{ $receiver }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="entry-filter-source" class="block text-sm font-semibold text-slate-700">Source</label>
                            <select id="entry-filter-source" class="form-field js-entry-filter" data-filter="source">
                                <option value="">All Sources</option>
                                @foreach ($sourceOptions as $source)
                                    <option value="{{ $source }}">{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="entry-filter-type" class="block text-sm font-semibold text-slate-700">Type</label>
                            <select id="entry-filter-type" class="form-field js-entry-filter" data-filter="type">
                                <option value="">All Types</option>
                                @foreach ($typeOptions as $type)
                                    <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button" id="entry-filter-reset" class="btn-secondary">
                            Reset Filters
                        </button>
                    </div>

                    <p id="entry-filter-count" class="mt-4 text-sm text-slate-600">
                        Showing {{ number_format($commissionEntryRows->count()) }} of {{ number_format($commissionEntryRows->count()) }} entries
                    </p>
                </div>

                <div class="max-h-[600px] overflow-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-semibold text-slate-700">Receiver</th>
                                <th class="px-5 py-3.5 text-left font-semibold text-slate-700">Source</th>
                                <th class="px-5 py-3.5 text-left font-semibold text-slate-700">Type</th>
                                <th class="px-5 py-3.5 text-left font-semibold text-slate-700">Order ID</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Rate</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Base Amount</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Commission</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($commissionEntryRows as $entry)
                                <tr class="js-entry-row hover:bg-slate-50"
                                    data-receiver="{{ $entry->receiverAffiliate?->name ?? '' }}"
                                    data-source="{{ $entry->sourceAffiliate?->name ?? '' }}"
                                    data-type="{{ $entry->commission_type }}">
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-950">{{ $entry->receiverAffiliate?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $entry->sourceAffiliate?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                                        @php
                                            $typeBadge = match ($entry->commission_type) {
                                                'personal' => 'badge-blue',
                                                'manager_bonus' => 'badge-purple',
                                                'l1_overriding' => 'badge-green',
                                                'l1_split_seller', 'l1_split_upline' => 'badge-amber',
                                                'l2_overriding' => 'badge-teal',
                                                'l3_overriding' => 'badge-gray',
                                                default => 'badge-gray',
                                            };
                                        @endphp
                                        <span class="badge {{ $typeBadge }}">
                                            {{ $entryTypeLabels[$entry->commission_type] ?? ucfirst(str_replace('_', ' ', $entry->commission_type)) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $entry->tiktokOrder?->order_id ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">{{ number_format((float) $entry->rate * 100, 2) }}%</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $entry->base_amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-950">RM {{ number_format((float) $entry->commission_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-slate-500">
                                        Tiada commission detail untuk run ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const summaryRows = @json($summaryRows);
            const summarySearch = document.getElementById('summary-search');
            const summaryReset = document.getElementById('summary-search-reset');
            const summaryCounter = document.getElementById('summary-filter-count');
            const summaryTableBody = document.getElementById('summary-table-body');
            const summaryCardList = document.getElementById('summary-card-list');
            const summaryEmptyTemplate = document.getElementById('summary-empty-template');
            let summarySortKey = 'affiliate';
            let summarySortDirection = 'asc';

            const formatMoney = (value) => `RM ${Number(value || 0).toLocaleString('en-MY', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const summaryFilteredRows = () => {
                const search = (summarySearch?.value || '').trim().toLowerCase();
                const numericKeys = ['total_sales', 'personal', 'manager_bonus', 'l1_earnings', 'l2_earnings', 'l3_earnings', 'total'];

                return summaryRows
                    .filter((row) => search === '' || row.affiliate.toLowerCase().includes(search))
                    .sort((a, b) => {
                        const aValue = numericKeys.includes(summarySortKey) ? Number(a[summarySortKey]) : String(a[summarySortKey] ?? '').toLowerCase();
                        const bValue = numericKeys.includes(summarySortKey) ? Number(b[summarySortKey]) : String(b[summarySortKey] ?? '').toLowerCase();
                        const result = aValue > bValue ? 1 : (aValue < bValue ? -1 : 0);

                        return summarySortDirection === 'asc' ? result : -result;
                    });
            };

            const updateSummarySortIcons = () => {
                document.querySelectorAll('[data-sort-icon]').forEach((icon) => {
                    icon.textContent = icon.dataset.sortIcon === summarySortKey
                        ? (summarySortDirection === 'asc' ? '↑' : '↓')
                        : '';
                });
            };

            const renderSummary = () => {
                const rows = summaryFilteredRows();

                if (summaryCounter) {
                    summaryCounter.textContent = `Showing ${rows.length.toLocaleString()} of ${summaryRows.length.toLocaleString()} affiliates`;
                }

                updateSummarySortIcons();

                if (summaryTableBody) {
                    summaryTableBody.innerHTML = rows.length
                        ? rows.map((row) => `
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-normal break-words px-3 py-3 font-medium leading-snug text-slate-950">${escapeHtml(row.affiliate)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">${formatMoney(row.total_sales)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">${formatMoney(row.personal)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">${formatMoney(row.manager_bonus)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">${formatMoney(row.l1_earnings)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">${formatMoney(row.l2_earnings)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right text-slate-700">${formatMoney(row.l3_earnings)}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-bold text-slate-950">${formatMoney(row.total)}</td>
                            </tr>
                        `).join('')
                        : '<tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No affiliate found for the current search.</td></tr>';
                }

                if (summaryCardList) {
                    summaryCardList.innerHTML = rows.length
                        ? rows.map((row) => `
                            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <h3 class="break-words text-sm font-bold leading-snug text-slate-950">${escapeHtml(row.affiliate)}</h3>
                                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Sales</dt><dd class="whitespace-nowrap font-semibold text-slate-900">${formatMoney(row.total_sales)}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Total</dt><dd class="whitespace-nowrap font-bold text-emerald-700">${formatMoney(row.total)}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Personal</dt><dd class="whitespace-nowrap text-slate-700">${formatMoney(row.personal)}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Manager</dt><dd class="whitespace-nowrap text-slate-700">${formatMoney(row.manager_bonus)}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Level 1</dt><dd class="whitespace-nowrap text-slate-700">${formatMoney(row.l1_earnings)}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Level 2</dt><dd class="whitespace-nowrap text-slate-700">${formatMoney(row.l2_earnings)}</dd></div>
                                    <div><dt class="text-xs font-bold uppercase text-slate-500">Level 3</dt><dd class="whitespace-nowrap text-slate-700">${formatMoney(row.l3_earnings)}</dd></div>
                                </dl>
                            </article>
                        `).join('')
                        : (summaryEmptyTemplate?.innerHTML || '<div class="px-5 py-10 text-center text-sm text-slate-500">No affiliate found for the current search.</div>');
                }
            };

            document.querySelectorAll('[data-summary-sort]').forEach((button) => {
                button.addEventListener('click', () => {
                    const nextKey = button.dataset.summarySort;
                    const numericKeys = ['total_sales', 'personal', 'manager_bonus', 'l1_earnings', 'l2_earnings', 'l3_earnings', 'total'];

                    if (summarySortKey === nextKey) {
                        summarySortDirection = summarySortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        summarySortKey = nextKey;
                        summarySortDirection = numericKeys.includes(nextKey) ? 'desc' : 'asc';
                    }

                    renderSummary();
                });
            });

            summarySearch?.addEventListener('input', renderSummary);
            summaryReset?.addEventListener('click', () => {
                if (summarySearch) {
                    summarySearch.value = '';
                }
                renderSummary();
            });

            renderSummary();

            const filters = {
                receiver: document.querySelector('[data-filter="receiver"]'),
                source: document.querySelector('[data-filter="source"]'),
                type: document.querySelector('[data-filter="type"]'),
            };
            const rows = Array.from(document.querySelectorAll('.js-entry-row'));
            const counter = document.getElementById('entry-filter-count');
            const resetButton = document.getElementById('entry-filter-reset');
            const totalRows = rows.length;

            const applyFilters = () => {
                const selected = {
                    receiver: filters.receiver?.value || '',
                    source: filters.source?.value || '',
                    type: filters.type?.value || '',
                };
                let visibleRows = 0;

                rows.forEach((row) => {
                    const matchesReceiver = selected.receiver === '' || row.dataset.receiver === selected.receiver;
                    const matchesSource = selected.source === '' || row.dataset.source === selected.source;
                    const matchesType = selected.type === '' || row.dataset.type === selected.type;
                    const isVisible = matchesReceiver && matchesSource && matchesType;

                    row.classList.toggle('hidden', !isVisible);

                    if (isVisible) {
                        visibleRows++;
                    }
                });

                if (counter) {
                    counter.textContent = `Showing ${visibleRows.toLocaleString()} of ${totalRows.toLocaleString()} entries`;
                }
            };

            Object.values(filters).forEach((filter) => {
                filter?.addEventListener('change', applyFilters);
            });

            resetButton?.addEventListener('click', () => {
                Object.values(filters).forEach((filter) => {
                    if (filter) {
                        filter.value = '';
                    }
                });
                applyFilters();
            });

            applyFilters();
        });
    </script>
@endsection
