@extends('layouts.auth')

@section('title', 'CSV Import Result')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">CSV Import Result</h1>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('admin.orders.upload') }}" class="btn-primary">
                        Upload Lagi
                    </a>
                    <a href="{{ route('admin.dashboard') }}" class="btn-secondary">
                        Dashboard
                    </a>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            @if (! empty($summary['missing_columns']))
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Missing required columns: {{ implode(', ', $summary['missing_columns']) }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-6">
                <div class="stat-card">
                    <p class="stat-label">Processed</p>
                    <p class="stat-value">{{ number_format($summary['total_rows']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Inserted</p>
                    <p class="stat-value stat-value-money">{{ number_format($summary['inserted_orders']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Updated</p>
                    <p class="stat-value">{{ number_format($summary['updated_orders'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Duplicates</p>
                    <p class="stat-value">{{ number_format($summary['skipped_duplicates']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Unmatched</p>
                    <p class="stat-value">{{ number_format($summary['skipped_unmatched_creators']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Invalid</p>
                    <p class="stat-value">{{ number_format($summary['skipped_invalid_rows']) }}</p>
                </div>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Sample Skipped Rows</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Reason</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Order ID</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Creator Username</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($summary['sample_skipped_rows'] as $row)
                                <tr>
                                    <td class="text-slate-700"><span class="badge badge-amber">{{ $row['reason'] }}</span></td>
                                    <td class="text-slate-700">{{ $row['order_id'] }}</td>
                                    <td class="text-slate-700">{{ $row['creator_username'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-slate-500">
                                        Tiada skipped row sample.
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
