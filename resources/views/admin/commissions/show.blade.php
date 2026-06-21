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
                    <h2 class="text-lg font-semibold text-slate-950">Affiliate Income Summary</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3.5 text-left font-semibold text-slate-700">Affiliate</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Total Sales</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Personal</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Manager Bonus</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Level 1 Earnings</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Level 2 Earnings</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Level 3 Earnings</th>
                                <th class="px-5 py-3.5 text-right font-semibold text-slate-700">Total Commission</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($summaries as $summary)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-950">{{ $summary['affiliate']->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $summary['total_sales'], 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $summary['personal'], 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $summary['manager_bonus'], 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $summary['l1_earnings'], 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $summary['l2_earnings'], 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">RM {{ number_format((float) $summary['l3_earnings'], 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-bold text-slate-950">RM {{ number_format((float) $summary['total'], 2) }}</td>
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
