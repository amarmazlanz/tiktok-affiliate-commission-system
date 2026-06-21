@extends('layouts.auth')

@section('title', 'Affiliate Detail')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Affiliate Detail</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.affiliates.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Affiliate List
                    </a>
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Dashboard
                    </a>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
            @if (session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('reset_password'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <p class="font-semibold text-amber-950">Copy this password now. It will not be shown again.</p>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold uppercase text-amber-700">Affiliate Name</dt>
                            <dd class="mt-1 font-medium">{{ session('reset_password.name') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-amber-700">Email</dt>
                            <dd class="mt-1 font-medium">{{ session('reset_password.email') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase text-amber-700">New Temporary Password</dt>
                            <dd class="mt-1 font-mono text-base font-bold">{{ session('reset_password.temporary_password') }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="app-card p-6 sm:p-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">{{ $affiliate->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $affiliate->email }}</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="btn-secondary">
                            Edit Affiliate
                        </a>
                        @if ($affiliate->user_id)
                            <form method="POST" action="{{ route('admin.affiliates.reset-password', $affiliate) }}" onsubmit="return confirm('Reset password untuk affiliate ini? Password lama tidak boleh dilihat semula dan akan digantikan.')">
                                @csrf
                                <button type="submit" class="btn-primary">
                                    Reset Password
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <dt class="font-medium text-slate-500">Phone</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->phone ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Status</dt>
                        <dd class="mt-1"><span class="badge {{ $affiliate->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($affiliate->status) }}</span></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Direct Upline</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->upline?->name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Direct Downline Count</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->direct_downlines_count }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Position / Status</dt>
                        <dd class="mt-1"><span class="badge {{ $affiliate->direct_downlines_count > 0 ? 'badge-green' : 'badge-gray' }}">{{ $affiliate->direct_downlines_count > 0 ? 'Manager' : 'Affiliate' }}</span></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">TikTok Accounts</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->tiktokAccounts->count() }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Last Password Reset</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->password_reset_at?->format('d/m/Y H:i') ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Reset By</dt>
                        <dd class="mt-1 text-slate-950">{{ $affiliate->passwordResetBy?->name ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Direct Downline</h2>
                    <p class="mt-1 text-sm text-slate-600">Affiliate with direct downline is treated as Manager for commission rule.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Name</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Email</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">TikTok Accounts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($affiliate->directDownlines as $downline)
                                <tr>
                                    <td class="font-medium text-slate-950">{{ $downline->name }}</td>
                                    <td class="text-slate-700">{{ $downline->email }}</td>
                                    <td><span class="badge {{ $downline->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($downline->status) }}</span></td>
                                    <td class="text-slate-700">{{ $downline->tiktok_accounts_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada direct downline.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h2 class="text-lg font-semibold text-slate-950">TikTok Accounts</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Username</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Normalized</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold text-slate-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($affiliate->tiktokAccounts as $account)
                                    <tr>
                                        <td class="font-medium text-slate-950">{{ $account->username }}</td>
                                        <td class="text-slate-700">{{ $account->username_normalized }}</td>
                                        <td>
                                            <span class="badge {{ $account->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                                                {{ ucfirst($account->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex justify-end">
                                                <form method="POST" action="{{ route('admin.affiliates.tiktok-accounts.destroy', [$affiliate, $account]) }}" onsubmit="return confirm('Deactivate TikTok account ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-danger">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                            Belum ada TikTok account.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="app-card p-6">
                    <h2 class="text-lg font-semibold text-slate-950">Tambah TikTok Account</h2>
                    <form method="POST" action="{{ route('admin.affiliates.tiktok-accounts.store', $affiliate) }}" class="mt-5 space-y-5">
                        @csrf

                        <div>
                            <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="@ali_shop1" required class="form-field">
                            <p class="mt-1 text-xs text-slate-500">Simbol @ akan dibuang semasa disimpan.</p>
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                            <select id="status" name="status" class="form-field">
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary w-full">
                            Tambah Account
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
@endsection
