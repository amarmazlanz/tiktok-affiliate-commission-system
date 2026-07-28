@php
    $money = fn ($value) => 'RM '.number_format((float) $value, 2);
@endphp

<div class="space-y-4">
    <div class="app-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold text-emerald-700">Commission Summary</p>
                <h2 class="mt-1 text-xl font-black text-slate-950" data-period-title>{{ $periodLabel }}</h2>
            </div>

            <form method="GET" action="{{ $periodRoute }}" class="flex flex-col gap-3" data-period-filter-form>
                @if (($commissionFilters['commission_type'] ?? '') !== '')
                    <input type="hidden" name="commission_type" value="{{ $commissionFilters['commission_type'] }}">
                @endif
                @if (($commissionFilters['source_affiliate'] ?? '') !== '')
                    <input type="hidden" name="source_affiliate" value="{{ $commissionFilters['source_affiliate'] }}">
                @endif

                <div class="grid gap-3 lg:grid-cols-[190px_1fr] lg:items-end">
                    <div>
                        <label for="period_type" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Period</label>
                        <select id="period_type" name="period_type" class="form-field min-w-44" data-period-type-select>
                            <option value="month" @selected(($periodFilters['period_type'] ?? 'month') === 'month')>Monthly</option>
                            <option value="today" @selected(($periodFilters['period_type'] ?? 'month') === 'today')>Today</option>
                            <option value="yesterday" @selected(($periodFilters['period_type'] ?? 'month') === 'yesterday')>Yesterday</option>
                            <option value="last_7_days" @selected(($periodFilters['period_type'] ?? 'month') === 'last_7_days')>Last 7 Days</option>
                            <option value="this_month" @selected(($periodFilters['period_type'] ?? 'month') === 'this_month')>This Month</option>
                            <option value="last_month" @selected(($periodFilters['period_type'] ?? 'month') === 'last_month')>Last Month</option>
                            <option value="custom" @selected(($periodFilters['period_type'] ?? 'month') === 'custom')>Custom Date</option>
                        </select>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2" data-month-year-controls>
                        <div>
                            <label for="month" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Month</label>
                            <select id="month" name="month" class="form-field min-w-40" data-period-select>
                                <option value="all" @selected($periodFilters['month'] === 'all')>All Months</option>
                                @foreach ($months as $monthNumber => $monthName)
                                    <option value="{{ $monthNumber }}" @selected((string) $periodFilters['month'] === (string) $monthNumber)>{{ $monthName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="year" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Year</label>
                            <select id="year" name="year" class="form-field min-w-36" data-period-select>
                                <option value="all" @selected($periodFilters['year'] === 'all')>All Years</option>
                                @foreach ($availableYears as $yearOption)
                                    <option value="{{ $yearOption }}" @selected((string) $periodFilters['year'] === (string) $yearOption)>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3" data-custom-date-controls>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative min-w-0 flex-1">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            </span>
                            <input type="text" id="date-range-picker" name="_date_display"
                                placeholder="Select custom date range..."
                                readonly autocomplete="off"
                                class="form-field w-full cursor-pointer bg-white pl-9" style="margin-top:0">
                        </div>
                        @if ($periodFilters['date_from'] || $periodFilters['date_to'])
                            <a href="{{ $periodRoute }}?period_type=month&month={{ $periodFilters['month'] }}&year={{ $periodFilters['year'] }}"
                               class="inline-flex items-center justify-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                &times; Reset
                            </a>
                        @endif
                    </div>
                    <p class="mt-2 text-xs font-medium text-slate-500">Custom date can include multiple months, for example 01 Apr 2026 to 18 Jun 2026.</p>
                </div>

                <input type="hidden" name="date_from" id="date-from-value" value="{{ $periodFilters['date_from']?->format('Y-m-d') ?? '' }}">
                <input type="hidden" name="date_to" id="date-to-value" value="{{ $periodFilters['date_to']?->format('Y-m-d') ?? '' }}">
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <p class="stat-label">Total Sales</p>
            <p class="stat-value stat-value-money">{{ $money($personalSales) }}</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Personal Commission</p>
            <p class="stat-value stat-value-money">{{ $money($commissionSummary['personal']) }}</p>
        </div>
        @if ($showManagerBonus ?? false)
            <div class="stat-card">
                <p class="stat-label">Manager Bonus</p>
                <p class="stat-value stat-value-money">{{ $money($commissionSummary['manager_bonus']) }}</p>
            </div>
        @else
            <div class="stat-card">
                <p class="stat-label">Total Overriding</p>
                <p class="stat-value stat-value-money">{{ $money($commissionSummary['total_overriding']) }}</p>
            </div>
        @endif
        <div class="stat-card">
            <p class="stat-label">Total Commission</p>
            <p class="stat-value stat-value-money">{{ $money($commissionSummary['total']) }}</p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="app-card p-5">
            <p class="stat-label">L1 Earnings</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $money($commissionSummary['l1_overriding'] + $commissionSummary['l1_split_seller'] + $commissionSummary['l1_split_upline']) }}</p>
        </div>
        <div class="app-card p-5">
            <p class="stat-label">L2 Earnings</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $money($commissionSummary['l2_overriding']) }}</p>
        </div>
        <div class="app-card p-5">
            <p class="stat-label">L3 Earnings</p>
            <p class="mt-2 text-xl font-black text-slate-950">{{ $money($commissionSummary['l3_overriding']) }}</p>
        </div>
    </div>
</div>

@isset($groupSalesData)
<div class="app-card p-5 sm:p-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-emerald-700">Group Overview</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Team Structure &amp; Sales</h2>
        </div>
        <span class="text-xs font-bold text-slate-400">{{ $periodLabel }}</span>
    </div>

    @isset($teamSummary)
    <div class="mt-4 grid grid-cols-3 gap-3">
        <div class="rounded-lg bg-slate-50 p-4 text-center">
            <p class="stat-label">Total Members</p>
            <p class="mt-1 text-2xl font-black text-slate-950">{{ number_format($teamSummary['total_count']) }}</p>
        </div>
        <div class="rounded-lg bg-teal-50 p-4 text-center">
            <p class="stat-label text-teal-700">Inhouse</p>
            <p class="mt-1 text-2xl font-black text-teal-900">{{ number_format($teamSummary['inhouse_count']) }}</p>
        </div>
        <div class="rounded-lg bg-amber-50 p-4 text-center">
            <p class="stat-label text-amber-700">Online</p>
            <p class="mt-1 text-2xl font-black text-amber-900">{{ number_format($teamSummary['online_count']) }}</p>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Level</th>
                    <th class="px-4 py-2.5 text-center text-xs font-bold uppercase tracking-wide text-teal-600">Inhouse</th>
                    <th class="px-4 py-2.5 text-center text-xs font-bold uppercase tracking-wide text-amber-600">Online</th>
                    <th class="px-4 py-2.5 text-center text-xs font-bold uppercase tracking-wide text-slate-500">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($teamSummary['level_breakdown'] as $row)
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-700">{{ $row['label'] }}</td>
                        <td class="px-4 py-3 text-center font-semibold text-teal-700">{{ number_format($row['inhouse']) }}</td>
                        <td class="px-4 py-3 text-center font-semibold text-amber-700">{{ number_format($row['online']) }}</td>
                        <td class="px-4 py-3 text-center font-black text-slate-950">{{ number_format($row['inhouse'] + $row['online']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endisset

    <div class="mt-4 grid grid-cols-3 gap-3">
        <div class="rounded-lg bg-emerald-50 p-4">
            <p class="stat-label text-emerald-700">Group Sales</p>
            <p class="mt-1 text-xl font-black text-emerald-900">RM {{ number_format($groupSalesData['group_total'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-teal-50 p-4">
            <p class="stat-label text-teal-700">Inhouse Sales</p>
            <p class="mt-1 text-xl font-black text-teal-900">RM {{ number_format($groupSalesData['inhouse_sales'], 2) }}</p>
        </div>
        <div class="rounded-lg bg-amber-50 p-4">
            <p class="stat-label text-amber-700">Online Sales</p>
            <p class="mt-1 text-xl font-black text-amber-900">RM {{ number_format($groupSalesData['online_sales'], 2) }}</p>
        </div>
    </div>
</div>
@endisset
