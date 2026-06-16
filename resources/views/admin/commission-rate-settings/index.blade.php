@extends('layouts.auth')

@section('title', 'Commission Settings')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Commission Settings</h1>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Dashboard</a>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8">
            @if (session('success'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Monthly Commission Rates</h2>
                        <p class="mt-1 text-sm text-slate-600">Manage personal, manager bonus, and overriding rates by month.</p>
                    </div>
                    <a href="{{ route('admin.commission-rate-settings.create') }}" class="btn-primary">
                        Add New Rate
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Month/Year</th>
                                <th class="text-left">Personal</th>
                                <th class="text-left">Manager Bonus</th>
                                <th class="text-left">L1</th>
                                <th class="text-left">L2</th>
                                <th class="text-left">L3</th>
                                <th class="text-left">Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($settings as $commissionRateSetting)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $months[$commissionRateSetting->month] }} {{ $commissionRateSetting->year }}</td>
                                    <td class="text-slate-700">{{ number_format((float) $commissionRateSetting->personal_rate * 100, 2) }}%</td>
                                    <td class="text-slate-700">{{ number_format((float) $commissionRateSetting->manager_bonus_rate * 100, 2) }}%</td>
                                    <td class="text-slate-700">{{ number_format((float) $commissionRateSetting->l1_rate * 100, 2) }}%</td>
                                    <td class="text-slate-700">{{ number_format((float) $commissionRateSetting->l2_rate * 100, 2) }}%</td>
                                    <td class="text-slate-700">{{ number_format((float) $commissionRateSetting->l3_rate * 100, 2) }}%</td>
                                    <td>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $commissionRateSetting->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ ucfirst($commissionRateSetting->status) }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.commission-rate-settings.edit', $commissionRateSetting) }}" class="btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-slate-500">Belum ada commission rate setting.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $settings->links() }}
            </div>
        </section>
    </main>
@endsection
