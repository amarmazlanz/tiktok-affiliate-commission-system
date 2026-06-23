@extends('layouts.auth')

@section('title', 'Affiliate Management')

@section('content')
    <main class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Admin</p>
                    <h1 class="text-xl font-semibold text-slate-950">Affiliate Management</h1>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Dashboard
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-950">Senarai Affiliate</h2>
                    <p class="mt-1 text-sm text-slate-600">Tambah, edit, dan nyahaktifkan affiliate.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.affiliates.hierarchy') }}" class="btn-secondary">
                        View Hierarchy
                    </a>
                    <a href="{{ route('admin.affiliates.import.create') }}" class="btn-secondary">
                        Import Affiliates
                    </a>
                    <a href="{{ route('admin.affiliates.export', request()->query()) }}" class="btn-primary bg-emerald-700" onclick="this.classList.add('opacity-75','pointer-events-none'); this.textContent='Exporting...';">
                        Export Affiliates
                    </a>
                    <a href="{{ route('admin.affiliates.create') }}" class="btn-primary">
                        Tambah Affiliate
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="GET" action="{{ route('admin.affiliates.index') }}" class="app-card mb-6 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="search" class="block text-xs font-bold uppercase text-slate-500">Search</label>
                    <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" placeholder="Name, code or TikTok username"
                        class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="group" class="block text-xs font-bold uppercase text-slate-500">Group</label>
                    <select id="group" name="group" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        <option value="">All Groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group }}" @selected(($filters['group'] ?? '') === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-xs font-bold uppercase text-slate-500">Type</label>
                    <select id="type" name="type" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        <option value="">All Types</option>
                        <option value="inhouse" @selected(($filters['type'] ?? '') === 'inhouse')>Inhouse</option>
                        <option value="external" @selected(($filters['type'] ?? '') === 'external')>Affiliate Luar</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-bold uppercase text-slate-500">Status</label>
                    <select id="status" name="status" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        <option value="">All Status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary w-full">Filter</button>
                    <a href="{{ route('admin.affiliates.index') }}" class="btn-secondary">Reset</a>
                </div>
            </form>

            <div class="app-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="app-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Affiliate</th>
                                <th class="text-left">Affiliate Code</th>
                                <th class="text-left">Group</th>
                                <th class="text-left">Type</th>
                                <th class="text-left">TikTok Accounts</th>
                                <th class="text-left">Login / Email</th>
                                <th class="text-left">Direct Upline</th>
                                <th class="text-right">Direct Downline</th>
                                <th class="text-left">Position</th>
                                <th class="text-left">Status</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($affiliates as $affiliate)
                                <tr>
                                    <td>
                                        <div class="whitespace-normal break-words leading-snug">
                                            @if ($affiliate->id)
                                                <a href="{{ route('admin.affiliates.show', $affiliate) }}"
                                                    class="cursor-pointer rounded-sm font-semibold text-slate-900 hover:text-emerald-700 hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                                    {{ $affiliate->name }}
                                                </a>
                                            @else
                                                <span class="font-semibold text-slate-950">{{ $affiliate->name }}</span>
                                            @endif
                                        </div>
                                        @if ($affiliate->phone)
                                            <div class="mt-1 text-xs text-slate-500">{{ $affiliate->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="font-mono text-slate-700">{{ $affiliate->affiliate_code ?: '-' }}</td>
                                    <td>
                                        <span class="badge badge-blue">{{ $affiliate->group_name ?: '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $affiliate->affiliate_type === 'external' ? 'badge-amber' : 'badge-teal' }}">
                                            {{ $affiliate->affiliate_type === 'external' ? 'Affiliate Luar' : 'Inhouse' }}
                                        </span>
                                    </td>
                                    <td class="text-slate-700">
                                        @if ($affiliate->tiktokAccounts->isNotEmpty())
                                            <div class="max-w-52 space-y-1">
                                                @foreach ($affiliate->tiktokAccounts->take(3) as $account)
                                                    <span class="inline-block rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{{ $account->username_normalized }}</span>
                                                @endforeach
                                                @if ($affiliate->tiktok_accounts_count > 3)
                                                    <span class="text-xs text-slate-500">+{{ $affiliate->tiktok_accounts_count - 3 }} more</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="text-slate-700">
                                        @if ($affiliate->affiliate_type === 'external' || ! $affiliate->user)
                                            <span class="badge badge-gray">No login access</span>
                                        @else
                                            <div class="font-mono text-xs text-slate-950">{{ $affiliate->affiliate_code ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ str_ends_with((string) $affiliate->user->email, '.local') ? 'Affiliate code login' : $affiliate->user->email }}</div>
                                        @endif
                                    </td>
                                    <td class="text-slate-700">{{ $affiliate->upline?->name ?: '-' }}</td>
                                    <td class="money text-slate-700">{{ $affiliate->direct_downlines_count }}</td>
                                    <td>
                                        <span class="badge {{ $affiliate->direct_downlines_count > 0 ? 'badge-green' : 'badge-gray' }}">
                                            {{ $affiliate->direct_downlines_count > 0 ? 'Manager' : 'Affiliate' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $affiliate->status === 'active' ? 'badge-green' : 'badge-gray' }}">
                                            {{ ucfirst($affiliate->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                                Detail
                                            </a>
                                            <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="btn-secondary px-3 py-1.5 text-xs">
                                                Edit
                                            </a>
                                            @if ($affiliate->user_id)
                                                <form method="POST" action="{{ route('admin.affiliates.reset-password', $affiliate) }}" onsubmit="return confirm('Reset password untuk affiliate ini? Password lama tidak boleh dilihat semula dan akan digantikan.')">
                                                    @csrf
                                                    <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">
                                                        Reset Password
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.affiliates.destroy', $affiliate) }}" onsubmit="return confirm('Deactivate affiliate ini?')">
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
                                    <td colspan="11" class="px-4 py-8 text-center text-slate-500">
                                        Belum ada affiliate.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $affiliates->links() }}
            </div>
        </section>
    </main>
@endsection
