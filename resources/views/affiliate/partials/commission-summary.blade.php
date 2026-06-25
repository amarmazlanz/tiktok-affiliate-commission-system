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

            <form method="GET" action="{{ $periodRoute }}" class="grid gap-3 sm:grid-cols-2" data-period-filter-form>
                @if (($commissionFilters['commission_type'] ?? '') !== '')
                    <input type="hidden" name="commission_type" value="{{ $commissionFilters['commission_type'] }}">
                @endif
                @if (($commissionFilters['source_affiliate'] ?? '') !== '')
                    <input type="hidden" name="source_affiliate" value="{{ $commissionFilters['source_affiliate'] }}">
                @endif

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
