@extends('layouts.auth')

@section('title', 'Commission Report')

@section('content')
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

        <section class="mx-auto max-w-6xl space-y-6 px-4 py-8">
            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-semibold uppercase text-slate-500">Period</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ $months[$commission->month] }} {{ $commission->year }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-semibold uppercase text-slate-500">Total Sales</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">RM {{ number_format((float) $commission->total_sales, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-semibold uppercase text-slate-500">Total Commission</p>
                    <p class="mt-2 text-xl font-semibold text-emerald-700">RM {{ number_format((float) $commission->total_commission, 2) }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-semibold uppercase text-slate-500">Status</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ ucfirst($commission->status) }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-950">Affiliate Income Summary</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Affiliate</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Personal</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Manager Bonus</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Overriding L1</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Overriding L2</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Overriding L3</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($summaries as $summary)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-950">{{ $summary['affiliate']->name }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $summary['personal'], 2) }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $summary['manager_bonus'], 2) }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $summary['overriding_l1'], 2) }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $summary['overriding_l2'], 2) }}</td>
                                    <td class="px-4 py-3 text-slate-700">RM {{ number_format((float) $summary['overriding_l3'], 2) }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-950">RM {{ number_format((float) $summary['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                        Tiada commission entry untuk run ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
