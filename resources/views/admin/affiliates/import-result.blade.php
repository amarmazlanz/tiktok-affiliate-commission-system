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

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                <div class="stat-card">
                    <p class="stat-label">Total Rows</p>
                    <p class="stat-value">{{ number_format($summary['total_rows'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Inhouse Created</p>
                    <p class="stat-value stat-value-money">{{ number_format($summary['inhouse_created'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">External Created</p>
                    <p class="stat-value">{{ number_format($summary['external_created'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Profiles Updated</p>
                    <p class="stat-value">{{ number_format($summary['updated'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">TikTok Added</p>
                    <p class="stat-value">{{ number_format($summary['tiktok_added'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Hierarchy Linked</p>
                    <p class="stat-value">{{ number_format($summary['hierarchy_linked'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Needs Mapping</p>
                    <p class="stat-value">{{ number_format($summary['needs_mapping'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Username Conflicts</p>
                    <p class="stat-value">{{ number_format($summary['username_conflicts'] ?? 0) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Skipped Rows</p>
                    <p class="stat-value">{{ number_format($summary['skipped'] ?? 0) }}</p>
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
                                <th class="text-left">Sheet / Group</th>
                                <th class="text-left">Section / Type</th>
                                <th class="text-left">Name</th>
                                <th class="text-left">Affiliate Code</th>
                                <th class="text-left">TikTok Username</th>
                                <th class="text-left">L1 Raw Value</th>
                                <th class="text-left">Upline Match</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Temporary Password</th>
                                <th class="text-left">Error / Remark</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($results as $result)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $result['sheet'] ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ ($result['section'] ?? '') === 'external' ? 'badge-amber' : 'badge-teal' }}">
                                            {{ ($result['section'] ?? '') === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                                        </span>
                                    </td>
                                    <td class="text-slate-700">{{ $result['name'] ?? '-' }}</td>
                                    <td class="font-mono text-slate-700">{{ $result['affiliate_code'] ?? '-' }}</td>
                                    <td class="text-slate-700">{{ $result['tiktok_username'] ?? '-' }}</td>
                                    <td class="text-slate-700">{{ $result['raw_l1'] ?? '-' }}</td>
                                    <td>
                                        @php
                                            $uplineBadge = match ($result['upline_match'] ?? '') {
                                                'Linked' => 'badge-green',
                                                'No Upline' => 'badge-gray',
                                                'Needs Mapping', 'Needs Review' => 'badge-amber',
                                                default => 'badge-gray',
                                            };
                                        @endphp
                                        <span class="badge {{ $uplineBadge }}">{{ $result['upline_match'] ?? '-' }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $badge = match ($result['status'] ?? '') {
                                                'Inhouse Created' => 'badge-green',
                                                'External Created' => 'badge-blue',
                                                'Profile Updated' => 'badge-teal',
                                                'Needs Mapping', 'Needs Review' => 'badge-amber',
                                                'Skipped' => 'badge-red',
                                                default => 'badge-red',
                                            };
                                        @endphp
                                        <div class="space-y-1">
                                            <span class="badge {{ $badge }}">{{ $result['status'] ?? 'Unknown' }}</span>
                                            @if (($result['tiktok_status'] ?? '-') !== '-')
                                                @php
                                                    $tiktokBadge = match ($result['tiktok_status'] ?? '') {
                                                        'TikTok Account Added' => 'badge-green',
                                                        'Already Exists' => 'badge-gray',
                                                        'Username Conflict', 'Skipped' => 'badge-red',
                                                        default => 'badge-gray',
                                                    };
                                                @endphp
                                                <span class="badge {{ $tiktokBadge }}">{{ $result['tiktok_status'] }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="font-mono text-slate-700">{{ $result['temporary_password'] ?? '-' }}</td>
                                    <td class="text-slate-700">{{ $result['error'] ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-5 py-10 text-center text-slate-500">
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
