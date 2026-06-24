@php
    $money = fn ($value) => 'RM '.number_format((float) $value, 2);
@endphp

<p class="border-b border-slate-100 bg-white px-6 py-4 text-sm text-slate-600" data-entry-result-count>
    Showing {{ number_format($commissionEntries->firstItem() ?? 0) }}-{{ number_format($commissionEntries->lastItem() ?? 0) }} of {{ number_format($commissionEntries->total()) }} entries
</p>

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
            @forelse ($commissionEntries as $entry)
                <tr class="hover:bg-slate-50">
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
                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">{{ $money($entry->base_amount) }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-950">{{ $money($entry->commission_amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-10 text-center text-slate-500">
                        Tiada commission detail untuk filter semasa.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="border-t border-slate-100 bg-slate-50 px-6 py-4" data-entry-pagination>
    {{ $commissionEntries->links() }}
</div>
