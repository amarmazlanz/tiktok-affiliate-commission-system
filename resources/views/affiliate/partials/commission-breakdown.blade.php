@php
    $money = fn ($value) => 'RM '.number_format((float) $value, 2);
@endphp

<div class="app-card overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Commission Breakdown</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $periodLabel }} · Showing {{ number_format($commissionEntries->firstItem() ?? 0) }}-{{ number_format($commissionEntries->lastItem() ?? 0) }} of {{ number_format($commissionEntries->total()) }} received entries
                </p>
            </div>

            <form method="GET" action="{{ route('affiliate.dashboard') }}" class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:grid-cols-[180px_220px_auto]" data-commission-filter-form>
                <input type="hidden" name="month" value="{{ $periodFilters['month'] }}">
                <input type="hidden" name="year" value="{{ $periodFilters['year'] }}">

                <div>
                    <label for="commission_type" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Commission Type</label>
                    <select id="commission_type" name="commission_type" class="form-field" data-auto-submit-select>
                        <option value="">All Types</option>
                        @foreach ($entryTypeLabels as $type => $label)
                            <option value="{{ $type }}" @selected(($commissionFilters['commission_type'] ?? '') === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="source_affiliate" class="block text-xs font-bold uppercase tracking-wide text-slate-500">Source Affiliate</label>
                    <select id="source_affiliate" name="source_affiliate" class="form-field" data-auto-submit-select>
                        <option value="">All Sources</option>
                        @foreach ($commissionSourceOptions as $source)
                            <option value="{{ $source->id }}" @selected((string) ($commissionFilters['source_affiliate'] ?? '') === (string) $source->id)>
                                {{ $source->name }}{{ $source->affiliate_code ? ' ('.$source->affiliate_code.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex self-end">
                    <a href="{{ route('affiliate.dashboard', ['month' => $periodFilters['month'], 'year' => $periodFilters['year']]) }}" class="btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="max-h-[520px] overflow-y-auto overflow-x-auto">
        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                <tr>
                    <th class="text-left">Source Affiliate</th>
                    <th class="text-left">Commission Type</th>
                    <th class="text-left">Order ID</th>
                    <th class="text-right">Eligible/Base Amount</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Commission Earned</th>
                    <th class="text-left">Date / Period</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($commissionEntries as $entry)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-normal break-words font-medium leading-snug text-slate-950">
                            {{ $entry->sourceAffiliate?->name ?? '-' }}
                            @if ($entry->sourceAffiliate?->affiliate_code)
                                <span class="mt-1 block font-mono text-xs text-slate-500">{{ $entry->sourceAffiliate->affiliate_code }}</span>
                            @endif
                        </td>
                        <td><span class="badge badge-blue">{{ $entryTypeLabels[$entry->commission_type] ?? str($entry->commission_type)->headline() }}</span></td>
                        <td class="whitespace-nowrap font-mono text-slate-700">{{ $entry->tiktokOrder?->order_id ?? '-' }}</td>
                        <td class="money text-slate-700">{{ $money($entry->base_amount) }}</td>
                        <td class="money text-slate-700">{{ number_format((float) $entry->rate * 100, 2) }}%</td>
                        <td class="money font-bold text-slate-950">{{ $money($entry->commission_amount) }}</td>
                        <td class="whitespace-nowrap text-slate-700">
                            @if ($entry->commissionRun)
                                {{ \Carbon\Carbon::create((int) $entry->commissionRun->year, (int) $entry->commissionRun->month, 1)->format('M Y') }}
                            @else
                                {{ $entry->created_at?->format('d/m/Y') ?? '-' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">No commission entries for the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-100 bg-slate-50 px-6 py-4">
        {{ $commissionEntries->links() }}
    </div>
</div>
