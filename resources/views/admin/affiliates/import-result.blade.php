@extends('layouts.auth')

@section('title', 'Affiliate Import Result')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Affiliate Import Result</h1>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.affiliates.import.create') }}" class="btn-primary">Import Again</a>
                    <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">Affiliate Management</a>
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
                    <p class="stat-label">Total Rows</p>
                    <p class="stat-value">{{ number_format($summary['total_rows']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Created</p>
                    <p class="stat-value stat-value-money">{{ number_format($summary['created']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Updated</p>
                    <p class="stat-value">{{ number_format($summary['updated']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">TikTok Added</p>
                    <p class="stat-value">{{ number_format($summary['tiktok_added']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Already Exists</p>
                    <p class="stat-value">{{ number_format($summary['already_exists']) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Skipped</p>
                    <p class="stat-value">{{ number_format($summary['skipped']) }}</p>
                </div>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Import Summary</h2>
                    <p class="mt-1 text-sm text-slate-600">Temporary passwords are shown once for newly created affiliate users.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Row</th>
                                <th class="text-left">Name</th>
                                <th class="text-left">Email</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Temporary Password</th>
                                <th class="text-left">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($results as $result)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $result['row'] }}</td>
                                    <td class="text-slate-700">{{ $result['name'] ?: '-' }}</td>
                                    <td class="text-slate-700">{{ $result['email'] ?: '-' }}</td>
                                    <td>
                                        @php
                                            $badge = match ($result['status']) {
                                                'Created' => 'badge-green',
                                                'Updated' => 'badge-blue',
                                                'TikTok Account Added' => 'badge-teal',
                                                'Already Exists' => 'badge-gray',
                                                default => 'badge-red',
                                            };
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ $result['status'] }}</span>
                                    </td>
                                    <td class="font-mono text-slate-700">{{ $result['temporary_password'] }}</td>
                                    <td class="text-slate-700">{{ $result['error'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                                        No affiliate rows imported.
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
