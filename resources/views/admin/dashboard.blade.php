@extends('layouts.auth')

@section('title', 'Admin Dashboard')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Dashboard</h1>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            @if (session('error'))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="app-card p-6 sm:p-7">
                <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">TikTok Affiliate Commission System</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Business Overview</h2>
                        <p class="mt-2 text-sm text-slate-600">Monitor affiliate sales, commission activity, imported orders, and fixed-rate commission runs.</p>
                    </div>
                    <p class="text-sm text-slate-500">{{ now()->format('d M Y') }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="stat-card">
                    <p class="stat-label">Total Sales This Month</p>
                    <p class="stat-value stat-value-money">RM {{ number_format($summary['total_sales_this_month'], 2) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Commission This Month</p>
                    <p class="stat-value stat-value-money">RM {{ number_format($summary['total_commission_this_month'], 2) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Affiliates</p>
                    <p class="stat-value">{{ number_format($summary['total_affiliates']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total TikTok Accounts</p>
                    <p class="stat-value">{{ number_format($summary['total_tiktok_accounts']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Orders Imported</p>
                    <p class="stat-value">{{ number_format($summary['total_orders_imported']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Commission Rate Mode</p>
                    <p class="stat-value">{{ $summary['active_rate_label'] }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Total Commission Runs</p>
                    <p class="stat-value">{{ number_format($summary['total_commission_runs']) }}</p>
                </div>
            </div>

            <div class="app-card p-6">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Quick Actions</h2>
                        <p class="mt-1 text-sm text-slate-600">Common admin workflows for daily operations.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.affiliates.index') }}" class="btn-primary">Manage Affiliates</a>
                    <a href="{{ route('admin.orders.upload') }}" class="btn-secondary">Upload CSV</a>
                    <a href="{{ route('admin.commissions.index') }}" class="btn-secondary">Run Commission</a>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-950">Recent Commission Runs</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr>
                                    <th class="text-left">Month/Year</th>
                                    <th class="text-right">Sales</th>
                                    <th class="text-right">Commission</th>
                                    <th class="text-left">Status</th>
                                    <th class="text-left">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($recentCommissionRuns as $run)
                                    <tr>
                                        <td class="font-medium text-slate-950">{{ $months[$run->month] ?? $run->month }} {{ $run->year }}</td>
                                        <td class="money text-slate-700">RM {{ number_format((float) $run->total_sales, 2) }}</td>
                                        <td class="money font-semibold text-emerald-700">RM {{ number_format((float) $run->total_commission, 2) }}</td>
                                        <td>
                                            <span class="badge {{ in_array($run->status, ['completed', 'final'], true) ? 'badge-green' : ($run->status === 'processing' ? 'badge-blue' : ($run->status === 'failed' ? 'badge-red' : 'badge-amber')) }}">
                                                {{ str($run->status)->headline() }}
                                            </span>
                                        </td>
                                        <td class="text-slate-700">{{ $run->created_at?->format('d/m/Y') ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-500">No commission runs yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-950">Top Affiliates</h2>
                                <p class="mt-1 text-sm text-slate-600">
                                    Showing top 5 affiliates by total sales for {{ $months[$selectedTopMonth] ?? $selectedTopMonth }} {{ $selectedTopYear }}.
                                </p>
                            </div>

                            <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-3 sm:grid-cols-2 lg:w-auto" id="top-affiliates-filter">
                                <div>
                                    <label for="top_month" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Month</label>
                                    <select id="top_month" name="top_month" class="form-field min-w-40" onchange="this.form.submit()">
                                        @foreach ($months as $number => $name)
                                            <option value="{{ $number }}" @selected((int) $selectedTopMonth === $number)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="top_year" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Year</label>
                                    <select id="top_year" name="top_year" class="form-field min-w-32" onchange="this.form.submit()">
                                        @foreach ($topAffiliateYears as $topYear)
                                            <option value="{{ $topYear }}" @selected((int) $selectedTopYear === (int) $topYear)>{{ $topYear }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr>
                                    <th class="text-left">Affiliate</th>
                                    <th class="text-right">Total Sales</th>
                                    <th class="text-right">Total Commission</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($topAffiliates as $row)
                                    <tr>
                                        <td class="font-medium text-slate-950">{{ $row['affiliate']->name }}</td>
                                        <td class="money text-slate-700">RM {{ number_format($row['total_sales'], 2) }}</td>
                                        <td class="money font-semibold text-emerald-700">RM {{ number_format($row['total_commission'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-8 text-center text-slate-500">
                                            No commission data available for the selected period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
