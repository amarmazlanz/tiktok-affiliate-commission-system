@extends('layouts.auth')

@section('title', 'Confirm Temporary Password Generation')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <section class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-emerald-700">Security Confirmation</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">Confirm Temporary Password Generation</h1>
                    <p class="mt-2 text-sm text-slate-600">Review every affected affiliate before replacing their current password.</p>
                </div>
                <a href="{{ route('admin.affiliates.index', array_filter($filters)) }}" class="btn-secondary">Cancel</a>
            </div>

            <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-red-900">
                <p class="font-black">This action will replace current passwords immediately.</p>
                <p class="mt-1 text-sm">
                    Existing passwords for these {{ number_format($affiliates->count()) }} Inhouse affiliates will stop working. New temporary passwords will be shown once and each affiliate must change their password after login.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="stat-card">
                    <p class="stat-label">Scope</p>
                    <p class="mt-2 text-lg font-black text-slate-950">{{ $scopeLabel }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Affiliates Affected</p>
                    <p class="stat-value">{{ number_format($affiliates->count()) }}</p>
                </div>
                <div class="stat-card">
                    <p class="stat-label">Previously Used Accounts</p>
                    <p class="stat-value">{{ number_format($affiliates->filter(fn ($affiliate) => $affiliate->user?->last_login_at || $affiliate->user?->password_changed_at)->count()) }}</p>
                </div>
            </div>

            @if ($scope === 'filtered')
                <div class="rounded-lg border border-red-300 bg-white px-4 py-3 text-sm font-bold text-red-800 shadow-sm">
                    Strong warning: All filtered Inhouse login accounts are included, including accounts whose users may already have changed their passwords.
                </div>
            @elseif ($scope === 'selected')
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                    Selected Affiliates is an explicit override. Previously used accounts in this selection will also be reset.
                </div>
            @endif

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-bold text-slate-950">Confirmation Summary</h2>
                    <p class="mt-1 text-sm text-slate-600">Password replacement is marked clearly for each account.</p>
                </div>
                <div class="max-h-[500px] overflow-x-auto overflow-y-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
                            <tr>
                                <th class="text-left">Affiliate</th>
                                <th class="text-left">Login ID</th>
                                <th class="text-left">Last Login</th>
                                <th class="text-left">Password Changed</th>
                                <th class="text-left">Current Requirement</th>
                                <th class="text-left">Existing Password Replaced</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($affiliates as $affiliate)
                                <tr>
                                    <td class="whitespace-normal break-words font-semibold text-slate-950">
                                        {{ $affiliate->name }}
                                        <span class="mt-1 block font-mono text-xs font-normal text-slate-500">{{ $affiliate->affiliate_code ?: '-' }}</span>
                                    </td>
                                    <td class="whitespace-normal break-words font-mono text-slate-700">
                                        {{ $affiliate->affiliate_code ?: $affiliate->user?->email }}
                                    </td>
                                    <td class="text-slate-700">{{ $affiliate->user?->last_login_at?->format('d/m/Y H:i') ?: 'Never' }}</td>
                                    <td class="text-slate-700">{{ $affiliate->user?->password_changed_at?->format('d/m/Y H:i') ?: 'Never' }}</td>
                                    <td>
                                        <span class="badge {{ $affiliate->user?->must_change_password ? 'badge-amber' : 'badge-gray' }}">
                                            {{ $affiliate->user?->must_change_password ? 'Must Change' : 'No' }}
                                        </span>
                                    </td>
                                    <td><span class="badge badge-red">Yes</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.affiliates.login-report.generate.confirm') }}" class="app-card p-6">
                @csrf
                <input type="hidden" name="confirmation_token" value="{{ $confirmationToken }}">
                <label class="flex items-start gap-3">
                    <input type="checkbox" required class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-sm font-semibold text-slate-700">
                        I confirm that I reviewed the {{ number_format($affiliates->count()) }} affected affiliates and understand their current passwords will stop working immediately.
                    </span>
                </label>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <a href="{{ route('admin.affiliates.index', array_filter($filters)) }}" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-danger px-5 py-2.5">Confirm and Generate Passwords</button>
                </div>
            </form>
        </section>
    </main>
@endsection
