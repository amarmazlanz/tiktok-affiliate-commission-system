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

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="app-card p-6 sm:p-7">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">{{ $affiliate->name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $affiliate->user ? ($affiliate->affiliate_code ?: $affiliate->user->email) : 'No login access' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="btn-secondary">
                            Edit Affiliate
                        </a>
                        @if ($affiliate->affiliate_type !== 'external' && $affiliate->user_id)
                            <form method="POST" action="{{ route('admin.affiliates.reset-password', $affiliate) }}" onsubmit="return confirm('This will replace the affiliate\\'s current password with a temporary password. Continue?')">
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
                        <dt class="font-medium text-slate-500">Group</dt>
                        <dd class="mt-1"><span class="badge badge-blue">{{ $affiliate->group_name ?: '-' }}</span></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Type</dt>
                        <dd class="mt-1"><span class="badge {{ $affiliate->affiliate_type === 'external' ? 'badge-amber' : 'badge-teal' }}">{{ $affiliate->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}</span></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Login ID</dt>
                        <dd class="mt-1 text-slate-950">
                            {{ $affiliate->affiliate_type === 'external' || ! $affiliate->user ? 'No login access' : ($affiliate->affiliate_code ?: $affiliate->user->email) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Login Access</dt>
                        <dd class="mt-1">
                            <span class="badge {{ $affiliate->affiliate_type !== 'external' && $affiliate->user ? 'badge-green' : 'badge-gray' }}">
                                {{ $affiliate->affiliate_type !== 'external' && $affiliate->user ? 'Active' : 'No login access' }}
                            </span>
                        </dd>
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
                    <div>
                        <dt class="font-medium text-slate-500">Must Change Password</dt>
                        <dd class="mt-1">
                            @if ($affiliate->affiliate_type === 'external' || ! $affiliate->user)
                                <span class="badge badge-gray">Not applicable</span>
                            @else
                                <span class="badge {{ $affiliate->user->must_change_password ? 'badge-amber' : 'badge-green' }}">
                                    {{ $affiliate->user->must_change_password ? 'Yes' : 'No' }}
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="app-card overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h2 class="text-lg font-semibold text-slate-950">Direct Downline</h2>
                    <p class="mt-1 text-sm text-slate-600">Affiliate with direct downline is treated as Manager for commission rule.</p>
                </div>

                <div class="max-h-[500px] overflow-x-auto overflow-y-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50 shadow-sm">
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
                                    <td class="whitespace-normal break-words font-medium leading-snug text-slate-950">{{ $downline->name }}</td>
                                    <td class="whitespace-normal break-words leading-snug text-slate-700">
                                        {{ $downline->affiliate_code ?: ($downline->email ?: 'No login access') }}
                                    </td>
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

        @if (session('reset_password'))
            <div id="reset-password-modal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4" role="dialog" aria-modal="true" aria-labelledby="reset-password-title">
                <div class="w-full max-w-lg rounded-lg bg-white shadow-2xl ring-1 ring-slate-900/10">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-sm font-bold text-emerald-700">Password reset successful</p>
                        <h2 id="reset-password-title" class="mt-1 text-xl font-black text-slate-950">Temporary Login Password</h2>
                    </div>
                    <div class="space-y-5 px-6 py-5">
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                            Copy this password now. It will not be shown again.
                        </p>
                        <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-bold uppercase text-slate-500">Affiliate</dt>
                                <dd class="mt-1 font-semibold text-slate-950">{{ session('reset_password.name') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase text-slate-500">Login ID</dt>
                                <dd class="mt-1 font-mono font-semibold text-slate-950">{{ session('reset_password.login_id') }}</dd>
                            </div>
                        </dl>
                        <div>
                            <label for="temporary-password-value" class="text-xs font-bold uppercase text-slate-500">Temporary Password</label>
                            <div class="mt-2 flex gap-2">
                                <input id="temporary-password-value" type="text" readonly
                                    value="{{ session('reset_password.temporary_password') }}"
                                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 font-mono text-base font-black text-slate-950">
                                <button type="button" id="copy-temporary-password" class="btn-primary">Copy Password</button>
                            </div>
                            <p id="copy-password-status" class="mt-2 hidden text-sm font-semibold text-emerald-700">Password copied.</p>
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                        <button type="button" id="close-reset-password-modal" class="btn-secondary">Close</button>
                    </div>
                </div>
            </div>
        @endif
    </main>

    @if (session('reset_password'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('reset-password-modal');
                const passwordInput = document.getElementById('temporary-password-value');
                const copyButton = document.getElementById('copy-temporary-password');
                const copyStatus = document.getElementById('copy-password-status');
                const closeButton = document.getElementById('close-reset-password-modal');

                const closeModal = () => modal?.remove();

                copyButton?.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(passwordInput.value);
                    } catch (error) {
                        passwordInput.select();
                        document.execCommand('copy');
                    }

                    copyStatus?.classList.remove('hidden');
                    copyButton.textContent = 'Copied';
                });

                closeButton?.addEventListener('click', closeModal);
                modal?.addEventListener('click', (event) => {
                    if (event.target === modal) closeModal();
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') closeModal();
                });
            });
        </script>
    @endif
@endsection
