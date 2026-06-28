@extends('layouts.auth')

@section('title', 'TikTok Accounts')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
            <div>
                <p class="text-sm font-bold text-emerald-700">Account Links</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">TikTok Accounts</h2>
                <p class="mt-2 text-sm text-slate-500">Accounts currently linked to {{ $affiliate->name }}. New usernames require admin approval before becoming active.</p>
                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                    Changing TikTok account status affects future order matching only. Historical approved commission reports are not changed.
                </p>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <div class="app-card overflow-hidden">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-950">TikTok Accounts</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                                <thead>
                                    <tr>
                                        <th class="text-left">Username</th>
                                        <th class="text-left">Normalized Username</th>
                                        <th class="text-left">Status</th>
                                        <th class="text-left">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($tiktokAccounts as $account)
                                        <tr>
                                            <td class="font-bold text-slate-950">{{ $account->username }}</td>
                                            <td class="font-mono text-slate-600">{{ $account->username_normalized }}</td>
                                            <td><span class="badge {{ $account->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($account->status) }}</span></td>
                                            <td class="whitespace-nowrap">
                                                @php
                                                    $nextStatus = $account->status === 'active' ? 'inactive' : 'active';
                                                    $confirmMessage = $nextStatus === 'inactive'
                                                        ? 'Are you sure you want to set this TikTok account as inactive? Orders using this username may no longer be matched to your active account for future processing.'
                                                        : 'Are you sure you want to reactivate this TikTok account?';
                                                @endphp
                                                <form method="POST" action="{{ route('affiliate.tiktok-accounts.status', $account) }}"
                                                    onsubmit="return confirm(@js($confirmMessage));">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                                    <button type="submit" class="{{ $nextStatus === 'inactive' ? 'btn-danger' : 'btn-secondary' }}">
                                                        Set {{ ucfirst($nextStatus) }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500">No TikTok accounts are linked to your profile.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="app-card overflow-hidden">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-bold text-slate-950">Pending TikTok Account Requests</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                                <thead>
                                    <tr>
                                        <th class="text-left">Requested Username</th>
                                        <th class="text-left">Status</th>
                                        <th class="text-left">Submitted At</th>
                                        <th class="text-left">Rejection Reason</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($pendingRequests as $request)
                                        <tr>
                                            <td class="font-mono text-slate-950">{{ $request->requested_username }}</td>
                                            <td>
                                                @if ($request->status === 'rejected')
                                                    <span class="badge badge-red">Rejected</span>
                                                @else
                                                    <span class="badge badge-amber">Pending Review</span>
                                                @endif
                                            </td>
                                            <td class="text-slate-600">{{ $request->created_at?->format('d/m/Y H:i') }}</td>
                                            <td class="whitespace-normal break-words text-slate-600">{{ $request->rejection_reason ?: '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No pending or rejected requests.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="app-card p-6">
                    <h2 class="text-lg font-bold text-slate-950">Request New TikTok Account</h2>
                    <p class="mt-1 text-sm text-slate-500">Submit a username for admin review. It will not become active until approved.</p>
                    <form method="POST" action="{{ route('affiliate.tiktok-accounts.store') }}" class="mt-5 space-y-5">
                        @csrf
                        <div>
                            <label for="username" class="block text-sm font-bold text-slate-700">TikTok Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="@your_shop" required maxlength="100" class="form-field">
                            <p class="mt-1 text-xs text-slate-500">The @ symbol will be removed automatically.</p>
                        </div>
                        <button type="submit" class="btn-primary w-full">Submit Request</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
@endsection
