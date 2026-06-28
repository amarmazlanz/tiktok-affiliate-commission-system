@extends('layouts.auth')

@section('title', 'Pending Registrations')

@section('content')
    @php
        $statusLabels = [
            'pending' => 'Pending',
            'duplicate_review' => 'Duplicate Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
        $statusBadges = [
            'pending' => 'badge-blue',
            'duplicate_review' => 'badge-amber',
            'approved' => 'badge-green',
            'rejected' => 'badge-red',
        ];
        $summary = [
            ['label' => 'Pending', 'status' => 'pending', 'class' => 'text-blue-700'],
            ['label' => 'Duplicate Review', 'status' => 'duplicate_review', 'class' => 'text-amber-700'],
            ['label' => 'Approved', 'status' => 'approved', 'class' => 'text-emerald-700'],
            ['label' => 'Rejected', 'status' => 'rejected', 'class' => 'text-red-700'],
            ['label' => 'Total Applications', 'status' => null, 'class' => 'text-slate-950'],
        ];
        $totalApplications = $summaryCounts->sum();
    @endphp

    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-emerald-700">Admin</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">Pending Registrations</h2>
                    <p class="mt-1 text-sm text-slate-600">Review applications submitted through public referral links.</p>
                </div>
                <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">
                    Affiliate Management
                </a>
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($summary as $card)
                    <div class="stat-card min-h-0">
                        <p class="stat-label">{{ $card['label'] }}</p>
                        <p class="mt-3 text-3xl font-black {{ $card['class'] }}">
                            {{ $card['status'] ? number_format((int) ($summaryCounts[$card['status']] ?? 0)) : number_format((int) $totalApplications) }}
                        </p>
                    </div>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.affiliate-registrations.index') }}" class="app-card mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-xs font-bold uppercase text-slate-500">Search</label>
                    <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, name, email, phone or TikTok"
                        class="form-field">
                </div>
                <div>
                    <label for="status" class="block text-xs font-bold uppercase text-slate-500">Status</label>
                    <select id="status" name="status" class="form-field">
                        <option value="">All Status</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="group" class="block text-xs font-bold uppercase text-slate-500">Group</label>
                    <select id="group" name="group" class="form-field">
                        <option value="">All Groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group }}" @selected(($filters['group'] ?? '') === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="referrer" class="block text-xs font-bold uppercase text-slate-500">Referrer / Upline</label>
                    <select id="referrer" name="referrer" class="form-field">
                        <option value="">All Referrers</option>
                        @foreach ($referrers as $referrer)
                            <option value="{{ $referrer->id }}" @selected((string) ($filters['referrer'] ?? '') === (string) $referrer->id)>
                                {{ $referrer->name }}{{ $referrer->affiliate_code ? ' ('.$referrer->affiliate_code.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="per_page" class="block text-xs font-bold uppercase text-slate-500">Rows</label>
                    <select id="per_page" name="per_page" class="form-field">
                        @foreach ([25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from" class="block text-xs font-bold uppercase text-slate-500">From Date</label>
                    <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-field">
                </div>
                <div>
                    <label for="to" class="block text-xs font-bold uppercase text-slate-500">To Date</label>
                    <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-field">
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="btn-primary">Filter</button>
                    <a href="{{ route('admin.affiliate-registrations.index') }}" class="btn-secondary">Reset</a>
                </div>
            </form>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <p class="text-sm font-bold text-slate-950">
                        Showing {{ number_format($applications->firstItem() ?? 0) }}-{{ number_format($applications->lastItem() ?? 0) }} of {{ number_format($applications->total()) }} applications
                    </p>
                </div>
                <div class="max-h-[620px] overflow-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="text-left">Application Reference</th>
                                <th class="text-left">Applicant Name</th>
                                <th class="text-left">Masked IC</th>
                                <th class="text-left">Phone</th>
                                <th class="text-left">Email</th>
                                <th class="text-left">TikTok Username</th>
                                <th class="text-left">Invited By / Proposed Upline</th>
                                <th class="text-left">Group</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Submitted At</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($applications as $application)
                                <tr>
                                    <td class="font-mono text-xs font-bold text-slate-700">{{ $application->application_reference }}</td>
                                    <td class="max-w-72 whitespace-normal break-words font-semibold leading-snug text-slate-950">{{ $application->full_name }}</td>
                                    <td class="font-mono text-slate-700">{{ $application->masked_nric }}</td>
                                    <td class="whitespace-nowrap text-slate-700">{{ $application->phone }}</td>
                                    <td class="max-w-56 break-words text-slate-700">{{ $application->email }}</td>
                                    <td class="font-mono text-slate-700">{{ $application->tiktok_username }}</td>
                                    <td class="max-w-64 whitespace-normal break-words text-slate-700">
                                        <span class="font-semibold">{{ $application->proposedUpline?->name ?? $application->referrer?->name ?? '-' }}</span>
                                        @if ($application->proposedUpline?->affiliate_code ?? $application->referrer?->affiliate_code)
                                            <div class="mt-1 font-mono text-xs text-slate-500">
                                                {{ $application->proposedUpline?->affiliate_code ?? $application->referrer?->affiliate_code }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-blue">{{ $application->proposed_group_name ?: '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusBadges[$application->status] ?? 'badge-gray' }}">
                                            {{ $statusLabels[$application->status] ?? ucfirst(str_replace('_', ' ', $application->status)) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-slate-600">{{ $application->submitted_at?->format('d M Y, h:i A') ?? '-' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.affiliate-registrations.show', $application->application_reference) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="px-4 py-10 text-center text-sm text-slate-500">
                                        No registration applications found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $applications->links() }}
            </div>
        </section>
    </main>
@endsection
